<?php

namespace Gchaumont\Query;

use App\Support\Elasticsearch\Elasticsearch;
use App\Support\Traits\When;
use Gchaumont\Query\Builder\HandlesFilters;
use Gchaumont\Query\Builder\HandlesModels;
use Gchaumont\Query\Builder\HandlesMonitoring;
use Gchaumont\Query\Builder\HandlesPagination;
use Gchaumont\Query\Builder\HandlesPayload;
use Gchaumont\Query\Builder\HandlesRelations;
use Gchaumont\Query\Builder\HandlesSorts;
use Gchaumont\Query\Builder\HasAggregations;
use Gchaumont\Query\Builder\PerformsQuery;
use Gchaumont\Query\Compound\Boolean;
use Gchaumont\Query\FullText\MultiMatchQuery;
use Gchaumont\Query\Term\Prefix;
use Gchaumont\Query\Term\Wildcard;

 /**
  *  Elasticsearch Query Builder.
  */
 class Builder
 {
     use When;
     use PerformsQuery;
     use HandlesSorts;
     use HandlesRelations;
     use HasAggregations;
     use HandlesPayload;
     use HandlesFilters;
     use HandlesModels;
     use HandlesPagination;
     use HandlesMonitoring;

     public readonly string $searchableModel;

     protected static $client;

     protected array $index;

     protected ?Query $query = null;

     protected ?Query $post_filter = null;

     protected ?string $collapse = null;

     protected array $ranks = [];

     protected array $source = [];

     protected array $suggest = [];

     protected ?array $fields = null;

     protected bool $profile = false;

     protected string $filterPath;

     public function __construct(public null|string $model = null)
     {
         if (!is_null($model)) {
             $this->searchableModel = $model;
         }
         if (!empty($this->searchableModel)) {
             // throw new \Exception('Missing searchable model for elastic query', 1);
             $this->index = is_array($this->searchableModel::searchableIndexName()) ? $this->searchableModel::searchableIndexName() : [$this->searchableModel::searchableIndexName()];
         }
     }

     public static function query(Query $query = null): self
     {
         return (new self())->when($query, fn ($self) => $self->setQuery($query));
     }

     public function tap(callable $callback): self
     {
         $callback($this);

         return $this;
     }

     public function setQuery(Query $query): self
     {
         $this->query = $query;

         return $this;
     }

     public function getQuery(): ?Query
     {
         return $this->query ??= new Boolean();
     }

     public function getPostFilter(): ?Query
     {
         return $this->post_filter ??= new Boolean();
     }

     public function index(array $index): self
     {
         $this->index = $index;

         return $this;
     }

     public function rank(string $field, int|float $boost = 1): self
     {
         $this->ranks[] = [
             'field' => $field,
             'boost' => $boost,
         ];

         return $this;
     }

     public function collapse(string $field): self
     {
         $this->collapse = $field;

         return $this;
     }

     public function select(array|string $fields): self
     {
         if (is_string($fields)) {
             $fields = [$fields];
         }
         $this->source = array_merge($this->source, $fields);

         return $this;
     }

     public function fields(array $fields): self
     {
         $this->fields = array_merge($this->fields, $fields);

         return $this;
     }

     public function searchField($fields, string $query, string $operator = 'AND', bool $fuzzy = false): self
     {
         return $this->must(
             (new MultiMatchQuery())
                 ->fields(is_array($fields) ? $fields : [$fields])
                 ->operator($operator)
                 ->query($query)
                 ->fuzziness(fuzziness: $fuzzy ? 'AUTO' : '')
         );
     }

     public function searchPrefix(string $field, string $query): self
     {
         return $this->must((new Prefix())->field($field)->value($query));
     }

     public function searchWildcard(string $field, string $query): self
     {
         return $this->should(
             (new Wildcard())->field($field)->value(strstr($query, '*') ? $query : '*'.trim($query).'*')
         );
     }

     public function profile(bool $profile = true): self
     {
         $this->profile = $profile;

         return $this;
     }

     public function explain(string $id)
     {
         $payload = $this->buildPayload();

         $payload['id'] = $id;

         return static::getClient()->explain($payload);
     }

     public function filterPath(string $filterPath): static
     {
         $this->filterPath = $filterPath;

         return $this;
     }

     public function suggest(string $name, string $text, string|array $field, int $size = null, string $type = 'term', string $sort = null, string $mode = null, int $min_doc_freq = null): static
     {
         $this->suggest[] = compact('name', 'text', 'field', 'size', 'type', 'sort', 'mode', 'min_doc_freq');

         return $this;
     }

     private static function getClient()
     {
         return static::$client ??= resolve(Elasticsearch::class);
     }
 }
