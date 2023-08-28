<?php

namespace backend\controllers;

use backend\models\Country;
use backend\models\search\statistics\ByNewsSearch;
use backend\models\search\statistics\CategoriesSearch;
use backend\models\search\statistics\CommonSearch;
use backend\models\search\statistics\CountrySearch;
use backend\models\search\statistics\PushNotifications;
use backend\models\Source;
use Carbon\Carbon;
use common\models\Article;
use common\services\StatisticsService;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Request;
use yii\web\Response;

class StatisticsController extends BaseController
{
    private $statistics;

    public function __construct($id, $module, StatisticsService $statistics, $config = [])
    {
        $this->statistics = $statistics;
        parent::__construct($id, $module, $config);
    }

    public function actionIndex()
    {
        $searchModel = new CommonSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        $languages = [];
        if (
            ($countryCode = Yii::$app->request->queryParams['CommonSearch']['country_id']) &&
            Yii::$app->request->queryParams['CommonSearch']['language'] &&
            $country = Country::findByCode($countryCode)
        ) {
            $languages = $country->getArticlesLanguages()
                ->indexBy('code')
                ->select('name')
                ->column();
        }
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'languages' => $languages
        ]);
    }

    public function actionNews()
    {
        $searchModel = new ByNewsSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        $sources = [];
        $languages = [];

        if ($countryCode = Yii::$app->request->queryParams['ByNewsSearch']['country_id']) {
            $sources = Source::find()
                ->indexBy('id'
                )->where(['country' => $countryCode])
                ->select('name')
                ->column();


            if (Yii::$app->request->queryParams['ByNewsSearch']['language']) {
                if ($country = Country::findByCode($countryCode)) {
                    $languages = $country->getArticlesLanguages()
                        ->indexBy('code')
                        ->select('name')
                        ->column();
                }
            }
        }

        if ($searchModel->country_id) {
            $topCalculatedTime = $searchModel->getSelectedCountry()->top_calculated_at ?? null;
        }

        if (!isset($topCalculatedTime)) {
            if ($topCalculatedTime = Yii::$app->cache->get('topCalculatedTime')) {
                $topCalculatedTime = Carbon::createFromTimestamp($topCalculatedTime);
            }
        }

        if ($topCalculatedTime) {
            $topCalculatedTime = $topCalculatedTime->timestamp;
            $topCalculatedTime = time() - $topCalculatedTime;
            $topCalculatedTime = round($topCalculatedTime / 60);
        }

        return $this->render('news', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'sources' => $sources,
            'languages' => $languages,
            'topCalculatedTime' => $topCalculatedTime
        ]);
    }

    public function actionCountries()
    {
        $searchModel = new CountrySearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('countries', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionCategories()
    {
        $searchModel = new CategoriesSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        if ($searchModel->countryCode) {
            if ($country = Country::findByCode($searchModel->countryCode)) {
                $languages = $country->getArticlesLanguages()
                    ->indexBy('code')
                    ->select('name')
                    ->column();
            }
        }

        return $this->render('categories', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'languages' => $languages
        ]);
    }


    public function actionPushNotifications()
    {
        $searchModel = new PushNotifications();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        $sources = [];
        $languages = [];

        if ($countryCode = Yii::$app->request->queryParams['PushNotifications']['country']) {
            $sources = Source::find()
                ->indexBy('id'
                )->where(['country' => $countryCode])
                ->select('name')
                ->column();


            if (Yii::$app->request->queryParams['PushNotifications']['articles_language']) {
                if ($country = Country::findByCode($countryCode)) {
                    $languages = $country->getArticlesLanguages()
                        ->indexBy('code')
                        ->select('name')
                        ->column();
                }
            }
        }


        return $this->render('push-notifications', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'sources' => $sources,
            'languages' => $languages,
            'pushesByCountry' => $searchModel->pushesByCountry()
        ]);
    }

    public function actionArticleTopLog(Request $request)
    {
        if ($article = Article::findOne($request->post('id'))) {
            $stat = $this->statistics->generateArticleTopLog($article->id);

            if (!$stat) {
                return 'Статистики пока нет...';
            }

            return $this->renderPartial('top-log', [
                'article' => $article,
                'stat' => $stat
            ]);
        }
        throw new NotFoundHttpException();
    }

    /**
     * @return array[]
     * @throws NotFoundHttpException
     */
    public function actionGetClassList()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (($sources = Source::find()->select(['name', 'id'])->asArray()->where(['country' => Yii::$app->request->post('depdrop_parents')[0]])->all()) !== null) {
            return ['output' => $sources];
        }
        else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    /**
     * @return array[]
     * @throws NotFoundHttpException
     */
    public function actionGetLanguagesList()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (($country = Country::findByCode(Yii::$app->request->post('depdrop_parents')[0])) !== null) {
            if (count($country->articlesLanguages)) {
                return ['output' => $country->getArticlesLanguages()->select(['name', 'code as id'])->asArray()->all()];
            }

            return ['output' => ''];
        }
        else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

}
