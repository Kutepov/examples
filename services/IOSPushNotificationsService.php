<?php namespace common\services\push;

use common\models\App;
use common\models\Article;
use common\models\PushNotification;
use Pushok\Client;
use Pushok\Notification;
use Pushok\Payload;
use Pushok\Response;

class IOSPushNotificationsService extends PushNotificationsService
{
    private $apnsClient;

    public function __construct(Client $apnsClient)
    {
        $this->apnsClient = $apnsClient;
    }

    public function pushArticle(Article $article): void
    {
        if (PushNotification::find()->where(['article_id' => $article->id, 'platform' => App::PLATFORM_IOS])->exists()) {
            return;
        }

        $appsQuery = App::find()
            ->withEnabledPushes()
            ->iosOnly()
            ->andWhere([
                'country' => $article->source->country,
                'articles_language' => $article->source->language
            ])
            ->asArray()
            ->batch(3000);

        foreach ($appsQuery as $apps) {
            $batchNotifications = [];
            foreach ($apps as $app) {
                $enabledCategories = (array)json_decode($app['enabled_categories'], true);
                $enabledSources = (array)json_decode($app['enabled_sources'], true);

                if (($enabledCategories && !in_array($article->category_name, $enabledCategories, true)) ||
                    ($enabledSources && !in_array($article->source_id, $enabledSources, true))
                ) {
                    continue;
                }

                $notificationLog = $this->createNotificationLog($article, $app);
                $this->apnsClient->addNotification(
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
        }

        $this->apnsClient->push();
    }

    private function createNotification(Article $article, string $deviceToken, string $internalId): Notification
    {
        $alert = Payload\Alert::create()
            ->setBody($article->title);

        $payload = Payload::create()
            ->setMutableContent(true)
            ->setAlert($alert)
            ->setSound('default')
            ->setCustomValue('data', [
                'type' => 'article',
                'id' => $article->id,
                'image' => $article->previewImageUrl,
                'trigger-id' => $internalId
            ]);

        return new Notification($payload, $deviceToken, $internalId);
    }
}