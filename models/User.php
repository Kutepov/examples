<?php namespace backend\models;

use Yii;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * @property-read $backendName
 */
class User extends \common\models\User
{
    public $appBan = false;

    public const SCENARIO_UPDATE = 'update';
    public const STATUSES_FOR_DROPDOWN = [
        self::STATUS_INACTIVE => 'Неактивен',
        self::STATUS_ACTIVE => 'Активен',
        self::STATUS_BANNED => 'Забанен',
    ];

    public const PLATFORMS_FOR_DROPDOWN = [
        User::PLATFORM_IOS => 'iOS',
        User::PLATFORM_ANDROID => 'Android'
    ];

    /**
     * @var UploadedFile
     */
    public $imageFile;

    /**
     * @return array
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules[] = ['imageFile', 'file', 'skipOnEmpty' => false, 'extensions' => 'png, jpg', 'except' => self::SCENARIO_UPDATE];
        $rules[] = ['email', 'email'];
        $rules[] = [['ip', 'useragent', 'geo', 'country_code', 'language_code', 'name'], 'string'];
        $rules[] = ['appBan', 'boolean'];
        return $rules;
    }

    public function attributeLabels()
    {
        return [
            'status' => 'Статус',
            'name' => 'Имя',
            'created_at' => 'Дата регистрации',
            'updated_at' => 'Изменен',
            'photo' => 'Фото',
            'country_code' => 'Код страны',
            'language_code' => 'Код языка',
            'imageFile' => 'Фото',
            'password' => 'Пароль',
            'socials' => 'Соц. сети',
            'comments' => 'Комментарии',
            'geo' => 'Страна',
            'platform' => 'Платформа',
            'shadow_ban' => 'Скрывать комментарии',
            'appBan' => 'Забанить по приложению'
        ];
    }

    public function upload()
    {
        if ($this->validate()) {
            $this->imageFile->saveAs(
                Yii::getAlias('@api') . '/web/uploads/photo/' .
                $this->username . '.' .
                $this->imageFile->extension
            );
            return true;
        }

        return false;
    }

    public function save($runValidation = true, $attributeNames = null)
    {
        $this->imageFile = UploadedFile::getInstance($this, 'imageFile');
        if ($this->imageFile instanceof UploadedFile) {
            $this->deleteImage();
            $this->photo = $this->imageFile instanceof UploadedFile ? 'user/' . $this->username . '.' . $this->imageFile->extension : '';
        }
        if (parent::save($runValidation, $attributeNames)) {
            if ($this->imageFile instanceof UploadedFile) {
                $this->upload();
            }
            return true;
        }
        return false;
    }

    private function deleteImage()
    {
        $pathToFile = Yii::getAlias('@api') . '/web/uploads/photo/' . $this->photo;
        if (is_file($pathToFile)) {
            FileHelper::unlink($pathToFile);
        }
    }

    public function afterFind()
    {
        if ($this->apps) {
            $this->appBan = $this->apps[0]->banned;
        }
        parent::afterFind();
    }

    public function afterSave($insert, $changedAttributes)
    {
        if ($this->apps) {
            foreach ($this->apps as $app) {
                $app->updateAttributes([
                    'banned' => $this->appBan
                ]);
            }
        }
        parent::afterSave($insert, $changedAttributes);
    }

    public function delete()
    {
        $this->deleteImage();
        return parent::delete();
    }

    /**
     * @return string
     */
    public function getAbsoluteUrlToImage()
    {
        return Yii::getAlias('@frontendBaseUrl') . '/' . $this->photo;
    }

    public function getBackendName()
    {
        return $this->name ?: '[#' . $this->id . ']';
    }
}