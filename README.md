Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist grinat/yii2-union-data-provider
```

or add

```
"grinat/yii2-union-data-provider": "^0.1.0"
```

to the require section of your `composer.json` file.

or

```
php /usr/bin/composer require --prefer-dist grinat/yii2-union-data-provider
```

or

```
php your_composer_path require --prefer-dist grinat/yii2-union-data-provider
```




Usage
------------
```
use grinat\uniondataprovider\UnionDataProvider;
use yii\base\Model;

class ListOfActivityUnionModel extends Model{

    public function search()
    {
        $dataProvider = new UnionDataProvider([
            // model which attributes use for sort data
            'searchModel' => $this,
            'options' => [
                // needed fields
                'select' => 'id,comment,created_at,user_id',
                'union' => [
                    [
                         // set in class path to model
                        'class' => 'app\modules\recipe\models\RecipeComment',
                        // aliases for fields which have diiferent name
                        // was return in that example as relationColumn
                        // if the names are the same, specify them in select
                        'selectAs' => ['recipe_id' => 'relationColumn'],
                        // set relation of you need corresponds to $query->width('createdUser')
                        // if in relation used column with alias, set it as [alias => relationName]
                        'withRel' => ['createdUser', 'relationColumn' => 'recipe']
                    ],
                    [
                        'class' => 'app\modules\coach\models\CoachDietReview',
                        'selectAs' => ['diet_id' => 'relationColumn'],
                        'withRel' => ['createdUser', 'relationColumn' => 'diet']
                    ],
                    [
                        'class' => 'app\modules\coach\models\CoachReview',
                        'selectAs' => ['coach_id' => 'relationColumn'],
                        'withRel' => ['createdUser', 'relationColumn' => 'coach.createdUser']
                    ]
                ]
            ],
            'sort' => [
                'defaultOrder' => ['created_at' => SORT_DESC],
            ],
            // string Set null if need primaryKey for relWith from data scheme
            // 'unionRelsPk' => 'id', 
            // Return data from provider As array or not 
            // 'asArray' => false,
            // Set false if dont need duplicates
            // 'unionAll' => false, 
            // if needed correct data-key in GrigView and etc
            // 'key' => 'id' 
        ]);

        return $dataProvider;
    }
}
```
The execute query is:
```
SELECT * 
FROM   ((SELECT "id", 
                "comment", 
                "created_at", 
                "user_id", 
                Concat('0') AS union_index, 
                "recipe_id" AS "relationColumn" 
         FROM   "recipe_comment") 
        UNION ALL 
        (SELECT "id", 
                "comment", 
                "created_at", 
                "user_id", 
                Concat('1') AS union_index, 
                "diet_id"   AS "relationColumn" 
         FROM   "coach_diet_review") 
        UNION ALL 
        (SELECT "id", 
                "comment", 
                "created_at", 
                "user_id", 
                Concat('2') AS union_index, 
                "coach_id"  AS "relationColumn" 
         FROM   "coach_review")) "dummy_table" 
ORDER  BY "created_at" DESC 
LIMIT  10 
```
With queries
```sql
SELECT * FROM "user" WHERE "id" IN (5, 13, 3)
SELECT * FROM "recipe_recipe" WHERE "id" IN (1, 2, 18, 9)
...etc
```