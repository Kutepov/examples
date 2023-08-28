<?php namespace common\models;

use Carbon\Carbon;
use common\behaviors\CarbonBehavior;
use common\components\scrapers\common\Scraper;
use common\components\validators\TimestampValidator;
use Yii;

/**
 * This is the model class for table "sources_exceptions".
 *
 * @property integer $id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string $source_id
 * @property integer $source_url_id
 * @property string $url
 * @property string $message
 * @property string $hash
 * @property string $code
 * @property string $data
 * @property string $type
 * @property integer $count
 *
 * @property Source $source
 * @property SourceUrl $sourceUrl
 */
class SourceException extends \yii\db\ActiveRecord
{
    public const ARTICLE_ITEM = 'item_exception';
    public const ARTICLE_BODY = 'body_exception';
    public const ARTICLE_DESCRIPTION = 'description_exception';
    public const EMPTY_ARTICLE_BODY = 'empty_article_body';
    public const WRONG_ARTICLE_BODY = 'wrong_article_body';
    public const EMPTY_ARTICLE_DESCRIPTION = 'empty_article_description';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'sources_exceptions';
    }

    public function behaviors()
    {
        return [
            [
                'class' => CarbonBehavior::class,
                'attributes' => ['created_at', 'updated_at']
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created_at', 'updated_at'], TimestampValidator::class],
            [['source_id', 'source_url_id'], 'required'],
            [['source_url_id', 'count'], 'integer'],
            [['data'], 'string'],
            [['source_id'], 'string', 'max' => 36],
            [['url', 'message'], 'string', 'max' => 640],
            [['code'], 'string', 'max' => 16],
            [['type', 'hash'], 'string', 'max' => 32],
            [['url', 'source_id'], 'unique', 'targetAttribute' => ['url', 'source_id']],
            [['source_id'], 'exist', 'skipOnError' => true, 'targetClass' => Source::class, 'targetAttribute' => ['source_id' => 'id']],
            [['source_url_id'], 'exist', 'skipOnError' => true, 'targetClass' => SourceUrl::class, 'targetAttribute' => ['source_url_id' => 'id']],
        ];
    }

    public static function create($type, Scraper $scraper, \Throwable $exception, ?string $url = null): void
    {
        $plainStackTrace = explode("\n", $exception->getTraceAsString());
        $firstLine = $plainStackTrace[0] ?? null;

        self::insertRecord([
            'type' => $type,
            'source_id' => $scraper->sourceId,
            'source_url_id' => $scraper->id,
            'url' => $url,
            'message' => $exception->getMessage(),
            'hash' => $firstLine ? md5($firstLine) : null,
            'code' => $exception->getCode(),
            'data' => $exception->getTraceAsString(),
            'count' => 1
        ]);
    }

    public static function createWarning($type, Scraper $scraper, ?array $data, ?string $url = null): void
    {
        self::insertRecord([
            'type' => $type,
            'source_id' => $scraper->sourceId,
            'source_url_id' => $scraper->id,
            'url' => $url,
            'data' => $data ? print_r($data, true) : null
        ]);
    }

    private static function insertRecord($data): void
    {
        $sql = Yii::$app->db->createCommand()->insert(self::tableName(), $data)->getRawSql();

        try {
            Yii::$app->db->createCommand(
                $sql .
                ' ON DUPLICATE KEY UPDATE
              count = count + 1,
              updated_at = CURRENT_TIMESTAMP'
            )->execute();
        } catch (\Throwable $e) {
        }
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'created_at' => 'Created At',
            'source_id' => 'Source ID',
            'source_url_id' => 'Source Url ID',
            'url' => 'Url',
            'message' => 'Message',
            'code' => 'Code',
            'data' => 'data',
            'type' => 'Type',
            'count' => 'Count',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSource()
    {
        return $this->hasOne(Source::class, ['id' => 'source_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSourceUrl()
    {
        return $this->hasOne(SourceUrl::class, ['id' => 'source_url_id']);
    }
}
