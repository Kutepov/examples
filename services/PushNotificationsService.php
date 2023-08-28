<?php namespace common\services\push;

use common\models\Article;
use common\models\PushNotification;

abstract class PushNotificationsService
{
    abstract public function pushArticle(Article $article): void;

    protected function createNotificationLog(Article $article, $app): PushNotification
    {
        $notification = new PushNotification([
            'article_id' => $article->id,
            'app_id' => $app['id'],
            'country' => $article->source->country,
            'articles_language' => $article->source->language,
            'platform' => $app['platform']
        ]);

        $notification->createUUID();

        return $notification;
    }

}