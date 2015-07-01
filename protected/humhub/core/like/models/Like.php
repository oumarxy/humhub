<?php

namespace humhub\core\like\models;

use humhub\models\Setting;
use Yii;

/**
 * This is the model class for table "like".
 *
 * The followings are the available columns in table 'like':
 * @property integer $id
 * @property integer $target_user_id
 * @property string $object_model
 * @property integer $object_id
 * @property string $created_at
 * @property integer $created_by
 * @property string $updated_at
 * @property integer $updated_by
 *
 * @package humhub.modules_core.like.models
 * @since 0.5
 */
class Like extends \humhub\core\content\components\activerecords\ContentAddon
{

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'like';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return array(
            array(['object_model', 'object_id'], 'required'),
            array(['id', 'object_id', 'target_user_id', 'created_by', 'updated_by'], 'integer'),
            array(['updated_at', 'created_at'], 'safe')
        );
    }

    /**
     * Like Count for specifc model
     */
    public static function GetLikes($objectModel, $objectId)
    {
        $cacheId = "likes_" . $objectModel . "_" . $objectId;
        $cacheValue = Yii::$app->cache->get($cacheId);

        if ($cacheValue === false) {
            $newCacheValue = Like::findAll(array('object_model' => $objectModel, 'object_id' => $objectId));
            Yii::$app->cache->set($cacheId, $newCacheValue, Setting::Get('expireTime', 'cache'));
            return $newCacheValue;
        } else {
            return $cacheValue;
        }
    }

    /**
     * After Save, delete LikeCount (Cache) for target object
     */
    public function afterSave($insert, $changedAttributes)
    {
        Yii::$app->cache->delete('likes_' . $this->object_model . "_" . $this->object_id);

        $activity = new \humhub\core\like\activities\Liked();
        $activity->source = $this;
        $activity->create();

        $notification = new \humhub\core\like\notifications\NewLike();
        $notification->source = $this;
        $notification->originator = $this->user;
        $notification->sendBulk($this->content->getUnderlyingObject()->getFollowers(null, true, true));

        return parent::afterSave($insert, $changedAttributes);
    }

    /**
     * Before Delete, remove LikeCount (Cache) of target object.
     * Remove activity
     */
    public function beforeDelete()
    {
        Yii::$app->cache->delete('likes_' . $this->object_model . "_" . $this->object_id);
        return parent::beforeDelete();
    }

}