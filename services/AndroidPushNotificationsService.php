<?php namespace common\services\push;

use common\models\App;
use common\models\Article;
use common\models\PushNotification;
use GuzzleHttp\Promise\Each;
use paragraph1\phpFCM\Client;
use paragraph1\phpFCM\Message;
use paragraph1\phpFCM\Notification;
use paragraph1\phpFCM\Recipient\Device;

class AndroidPushNotificationsService extends PushNotificationsService
{
    private $fcmClient;

    public function __construct(Client $client)
    {
        $this->fcmClient = $client;
    }

    public function pushArticle(Article $article): void
    {
        if (PushNotification::find()->where(['article_id' => $article->id, 'platform' => App::PLATFORM_ANDROID])->exists()) {
            return;
        }

        $appsQuery = App::find()
            ->withEnabledPushes()
            ->androidOnly()
            ->andWhere([
                'country' => $article->source->country,
                'articles_language' => $article->source->language
            ])
            ->asArray()
            ->batch(1000);

        foreach ($appsQuery as $apps) {
            $batchNotifications = [];
            $asyncRequests = [];
            foreach ($apps as $app) {
                $enabledCategories = (array)json_decode($app['enabled_categories'], true);
                $enabledSources = (array)json_decode($app['enabled_sources'], true);

                if (($enabledCategories && !in_array($article->category_name, $enabledCategories, true)) ||
                    ($enabledSources && !in_array($article->source_id, $enabledSources, true))
                ) {
                    continue;
                }

                $notificationLog = $this->createNotificationLog($article, $app);
                $asyncRequests[] = $this->fcmClient->sendAsync(
                    $this->createNotification(
                        $article,
                        $app['push_token'],
                        $notificationLog->id
                    )
                );
                $batchNotifications[] = $notificationLog->toArray();
            }

            if (count($batchNotifications)) {
                \Yii::$app->db->createCommand()->batchInsertIgnoreFromArray(
                    PushNotification::tableName(),
                    $batchNotifications
                )->execute();
            }

            if (count($asyncRequests)) {
                Each::ofLimit($asyncRequests, 32)->wait();
            }
            sleep(30);
        }
    }

    private function createNotification(Article $article, string $deviceToken, string $internalId): Message
    {
        $message = new Message();
        $notification = new Notification($article->title, $article->description);
        $message->setNotification($notification);
        $message->addRecipient(new Device($deviceToken));
        $message->setPriority(Message::PRIORITY_HIGH);
        $message->setData([
            'title' => $article->title,
            'type' => 'article',
            'id' => $article->id,
            'image' => $article->previewImageUrl,
            'trigger-id' => $internalId,
            'description' => $article->description
        ]);

        return $message;
    }
}