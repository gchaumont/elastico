<?php

namespace Elastico\Query;

use Elastico\Connection;
use Elastico\Helpers\When;
use Elastico\Query\Builder\HandlesFilters;
use Elastico\Query\Builder\HandlesPagination;
use Elastico\Query\Builder\HandlesPayload;
use Elastico\Query\Builder\HandlesScopedQueries;
use Elastico\Query\Builder\HandlesSorts;
use Elastico\Query\Builder\HasAggregations;
use Elastico\Query\Compound\Boolean;
use Elastico\Query\FullText\MultiMatchQuery;
use Elastico\Query\Term\Prefix;
use Elastico\Query\Term\Wildcard;

 /**
  *  Elasticsearch Query Builder.
  */
 class Builder
 {
     use When;
     use HandlesSorts;
     use HandlesScopedQueries;
     use HasAggregations;
     use HandlesPayload;
     use HandlesFilters;
     use HandlesPagination;

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

     public function __construct(
         protected Connection $connection
     ) {
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

     public function index(string|array $index): self
     {
         $this->index = is_array($index) ? $index : [$index];

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

     private function getConnection(): Connection
     {
         return $this->connection;
     }
 }
