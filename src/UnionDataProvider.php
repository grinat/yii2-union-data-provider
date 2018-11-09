<?php
/**
 * @email paladin2012gnu@gmail.com
 * @author Gabdrashitov Rinat
 */
namespace grinat\uniondataprovider;

use yii\data\ActiveDataProvider;
use yii\data\BaseDataProvider;
use yii\db\Query;

/**
 * Use example
 * 
 * class ListOfActivityUnionModel extends Model{

 * public $id;
 * public $created_at;
 *
 *  public function search(){
 * 
 *       $dataProvider = new UnionDataProvider([
 *            'searchModel' => $this,
 *            'options' => $this->options,
 *            'sort' => [
 *               'defaultOrder' => ['created_at' => SORT_DESC],
 *            ],
 *            // if needed correct data-key in GrigView and etc
 *            // 'key' => 'id' 
 *       ]);
 *       
 *       return $dataProvider;
 *   }
 * 
 * }
 */

class UnionDataProvider extends ActiveDataProvider { /*extends BaseDataProvider implements DataProviderInterface{*/

    /**
     * @var array Example of config options
     *    [
     *        'select' => 'id,comment,created_at,user_id',
     *        'union' => [
     *            [
     *               'class' => 'app\modules\coach\models\CoachDietComment',
     *               'selectAs' => ['diet_id' => 'relationColumn'],
     *               //'where' => ['>','diet_id',5], //Yii2 activequery where style
     *               'withRel' => ['createdUser', 'relationColumn' => 'diet']
     *            ],
     *            [
     *                'class' => 'app\modules\coach\models\CoachDietReview',
     *                'selectAs' => ['diet_id' => 'relationColumn'],
     *               //'where' => ['diet_id'=>20], //Yii2 activequery where style     
     *                'withRel' => ['createdUser', 'relationColumn' => 'diet']
     *            ],
     *            [
     *                'class' => 'app\modules\coach\models\CoachReview',
     *                'selectAs' => ['coach_id' => 'relationColumn'],
     *               //'where' => ['or',['coach_id'=>20],['coach_id'=>50]], //Yii2 activequery where style     
     *                'withRel' => ['createdUser', 'relationColumn' => 'coach.createdUser']
     *            ]
     *        ]
     *    ]
     */
    public $options;
    
    /**
     *
     * @var boolen Return data from provider As array or not 
     */
    public $asArray = false;
    
    /**
     *
     * @var boolen Set false if dont need duplicates
     */
    public $unionAll = true;

    /**
     * @var \yii\base\Model virtual model
     * which attributes use for sort data
     */
    public $searchModel;
    
    /**
     * @var string Set null if need primaryKey for relWith from data scheme
     */
    public $unionRelsPk = 'id';

    public function init()
    {
        parent::init();
        
        if(!isset($this->options['select'])){
            $this->options['select'] = '';
        }
        
        $mainQ = null;
        foreach($this->options['union'] as $index => $option){
            $columns = [];
            if(is_array($this->options['select'])){
                $columns = $this->options['select'];
            } else {
                if($this->options['select']){
                    $columns = explode(',', $this->options['select']);
                }
            }
            // little hack, else yii2 quted index
            $columns[] = 'concat(\''.$index.'\') AS union_index';
            if(isset($option['selectAs'])){
                foreach ($option['selectAs'] as $col => $colAs) {
                    $columns[] = $col . ' AS ' . $colAs;
                }
            }
            /* @var $model \yii\db\ActiveRecord */
            $model = new $option['class'];
            $q = $model::find()->select($columns);
            
            
            if(!empty($option['where'])){
                $q->andWhere($option['where']);
            }
            
            if($mainQ === null){
                $mainQ = $q;
            } else {
                $mainQ->union($q, $this->unionAll);
            }
        }
        /* @var query \yii\db\Query */
        $this->query = (new Query())->from(['dummy_table' => $mainQ]);
        
    }
    
    /**
     * @inheritdoc
     */
    protected function prepareModels()
    {
        $dataArr = parent::prepareModels();

        // get relations info
        $relationsParamsByRelation = [];
        $relationsDataKeys = [];
        $relationsOfWithRel = [];
        $relationsPk = [];
        foreach($this->options['union'] as $option){
            if (isset($option['withRel'])) {
                $modelClass = $option['class'];
                /* @var $model \yii\db\ActiveRecord */
                $model = new $modelClass;
                foreach ($option['withRel'] as $relKey => $relName) {
                    if(strpos($relName, '.') !== false){
                        $childs = explode('.', $relName);
                        $relName = $childs[0];
                        unset($childs[0]);
                        $relationsOfWithRel[$relName] = implode('.', $childs);
                    }
                    $relationsParamsByRelation[$relName] = $model->getRelation($relName);
                    if($this->unionRelsPk === null){
                        $relationsPk[$relName] = $model::primaryKey()[0];
                    }else{
                        $relationsPk[$relName] = $this->unionRelsPk;
                    }
                    if (is_numeric($relKey)) {
                        $relationsDataKeys[$relName] = $relationsParamsByRelation[$relName]->link[$relationsPk[$relName]];
                    } else {
                        $relationsDataKeys[$relName] = $relKey;
                    }
                }
            }
        }
        
        // get id from relations
        $relationsIds = [];
        foreach($dataArr as $data){
            foreach($relationsParamsByRelation as $relName => $param){
                $dataKey = $relationsDataKeys[$relName];
                if(isset($data[$dataKey])){
                    $relationsIds[$relName][] = $data[$dataKey]; 
                } 
            }
        }
        
        // get relations
        $relations = [];
        $relationsModelKeys = [];
        foreach($relationsParamsByRelation as $relName => $param){
            if(!isset($relationsIds[$relName])){
                continue;
            }
            
            $where = [];
            foreach($param->link as $fieldId => $fieldName){
                if($fieldId == $relationsPk[$relName]){
                    $relationsModelKeys[$relName] = $fieldName;
                    $where[$fieldId] = array_unique($relationsIds[$relName]);
                    $idField = $fieldId;
                }
            }
            if(is_array($param->where)){
                $where = array_merge($where, $param->where);
            }
            /* @var $class \yii\db\ActiveRecord */
            $class = $param->modelClass;
            $q = $class::find()
                    ->where($where)
                    ->indexBy($idField)
                    ->asArray($this->asArray);
            
            if(isset($relationsOfWithRel[$relName])){
                $q->with($relationsOfWithRel[$relName]);
            }
            
            $relations[$relName] = $q->all();
        }

        //print_r($relations);die;
        // prepare models
        $models = [];
        foreach($dataArr as $data){
            $option = $this->options['union'][$data['union_index']];
            
            if (isset($option['selectAs'])) {
                foreach ($option['selectAs'] as $col => $colAs) {
                    $data[$col] = $data[$colAs];
                }
            }

            if($this->asArray === false){
                /* @var $model \yii\db\ActiveRecord */
                $model = new $option['class'];
                $model->setAttributes($data, false);
            } else {
                $model = $data;
            }
            
            if (!isset($option['withRel'])) {
                $models[] = $model;
                continue;
            }
            
            // set relations
            foreach($option['withRel'] as $relKey => $relName){
                // echo "$relKey => $relName  ".$relationsModelKeys[$relName]."\n";
                if(strpos($relName, '.') !== false){
                    $childs = explode('.', $relName);
                    $relName = $childs[0];
                }
                
                if(!isset($relationsModelKeys[$relName])){
                    continue;
                }
                $modelRelId = $relationsModelKeys[$relName];
                if($this->asArray === false){
                    if (isset($model->$modelRelId)) {
                        if(isset($relations[$relName][$model->$modelRelId])){
                            $model->populateRelation($relName, $relations[$relName][$model->$modelRelId]);
                        }else{
                            $model->populateRelation($relName, null);
                        }
                    }
                } else {
                    if (isset($model[$modelRelId])) {
                        if(isset($relations[$relName][$model[$modelRelId]])){
                            $model[$relName] = $relations[$relName][$model[$modelRelId]];
                        } else {
                            $model[$relName] = null;
                        }
                    }
                }
            }

            $models[] = $model;
        }
        
        // print_r($models);die;
        return $models;
    }

    /**
     * @inheritdoc
     */
    protected function prepareTotalCount()
    {
        return parent::prepareTotalCount();
    }
    
    /**
     * @inheritdoc
     */
    protected function prepareKeys($models)
    {
       
        $keys = [];
        if ($this->key !== null) {
            foreach ($models as $model) {
                if (is_string($this->key)) {
                    $keys[] = $model[$this->key];
                } else {
                    $keys[] = call_user_func($this->key, $model);
                }
            }

            return $keys;
        } 

        return array_keys($models);
    }

    public function setSort($value)
    {
        BaseDataProvider::setSort($value);
        if (($sort = $this->getSort()) !== false) {
            /* @var $model \yii\base\Model */
            $model = $this->searchModel;
            if (empty($sort->attributes)) {
                foreach ($model->attributes() as $attribute) {
                    $sort->attributes[$attribute] = [
                        'asc' => [$attribute => SORT_ASC],
                        'desc' => [$attribute => SORT_DESC],
                        'label' => $model->getAttributeLabel($attribute),
                    ];
                }
            } else {
                foreach ($sort->attributes as $attribute => $config) {
                    if (!isset($config['label'])) {
                        $sort->attributes[$attribute]['label'] = $model->getAttributeLabel($attribute);
                    }
                }
            }
        }
    }
}
