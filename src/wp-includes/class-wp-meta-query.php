<?php
/**
 * Meta API: WP_Meta_Query class
 *
 * @package WordPress
 * @subpackage Meta
 * @since 4.4.0
 */

/**
 * Core class used to implement meta queries for the Meta API.
 *
 * Used for generating SQL clauses that filter a primary query according to metadata keys and values.
 *
 * WP_Meta_Query is a helper that allows primary query classes, such as WP_Query and WP_User_Query,
 *
 * to filter their results by object metadata, by generating `JOIN` and `WHERE` subclauses to be attached
 * to the primary SQL query string.
 *
 * @since 3.2.0
 */
#[AllowDynamicProperties]
class WP_Meta_Query {
	/**
	 * Array of metadata queries.
	 *
	 * See WP_Meta_Query::__construct() for information on meta query arguments.
	 *
	 * @since 3.2.0
	 * @var array
	 */
	public $queries = array();

	/**
	 * The relation between the queries. Can be one of 'AND' or 'OR'.
	 *
	 * @since 3.2.0
	 * @var string
	 */
	public $relation;

	/**
	 * Database table to query for the metadata.
	 *
	 * @since 4.1.0
	 * @var string
	 */
	public $meta_table;

	/**
	 * Column in meta_table that represents the ID of the object the metadata belongs to.
	 *
	 * @since 4.1.0
	 * @var string
	 */
	public $meta_id_column;

	/**
	 * Database table that where the metadata's objects are stored (eg $wpdb->users).
	 *
	 * @since 4.1.0
	 * @var string
	 */
	public $primary_table;

	/**
	 * Column in primary_table that represents the ID of the object.
	 *
	 * @since 4.1.0
	 * @var string
	 */
	public $primary_id_column;

	/**
	 * A flat list of table aliases used in JOIN clauses.
	 *
	 * @since 4.1.0
	 * @var array
	 */
	protected $table_aliases = array();

	/**
	 * A flat list of clauses, keyed by clause 'name'.
	 *
	 * @since 4.2.0
	 * @var array
	 */
	protected $clauses = array();

	/**
	 * Whether the query contains any OR relations.
	 *
	 * @since 4.3.0
	 * @var bool
	 */
	protected $has_or_relation = false;

	/**
	 * Whether EXISTS subquery optimization is active for the current get_sql() call.
	 *
	 * When true, eligible single-clause meta queries use a correlated EXISTS subquery
	 * instead of a JOIN, which avoids adding a table to the FROM clause and allows
	 * MySQL to short-circuit via index lookup on (meta_id_column, meta_key).
	 *
	 * @since 7.0.0
	 * @var bool
	 */
	private $use_exists_subquery = false;

	/**
	 * Constructor.
	 *
	 * @since 3.2.0
	 * @since 4.2.0 Introduced support for naming query clauses by associative array keys.
	 * @since 5.1.0 Introduced `$compare_key` clause parameter, which enables LIKE key matches.
	 * @since 5.3.0 Increased the number of operators available to `$compare_key`. Introduced `$type_key`,
	 *              which enables the `$key` to be cast to a new data type for comparisons.
	 *
	 * @param array $meta_query {
	 *     Array of meta query clauses. When first-order clauses or sub-clauses use strings as
	 *     their array keys, they may be referenced in the 'orderby' parameter of the parent query.
	 *
	 *     @type string $relation Optional. The MySQL keyword used to join the clauses of the query.
	 *                            Accepts 'AND' or 'OR'. Default 'AND'.
	 *     @type array  ...$0 {
	 *         Optional. An array of first-order clause parameters, or another fully-formed meta query.
	 *
	 *         @type string|string[] $key         Meta key or keys to filter by.
	 *         @type string          $compare_key MySQL operator used for comparing the $key. Accepts:
	 *                                            - '='
	 *                                            - '!='
	 *                                            - 'LIKE'
	 *                                            - 'NOT LIKE'
	 *                                            - 'IN'
	 *                                            - 'NOT IN'
	 *                                            - 'REGEXP'
	 *                                            - 'NOT REGEXP'
	 *                                            - 'RLIKE'
	 *                                            - 'EXISTS' (alias of '=')
	 *                                            - 'NOT EXISTS' (alias of '!=')
	 *                                            Default is 'IN' when `$key` is an array, '=' otherwise.
	 *         @type string          $type_key    MySQL data type that the meta_key column will be CAST to for
	 *                                            comparisons. Accepts 'BINARY' for case-sensitive regular expression
	 *                                            comparisons. Default is ''.
	 *         @type string|string[] $value       Meta value or values to filter by.
	 *         @type string          $compare     MySQL operator used for comparing the $value. Accepts:
	 *                                            - '='
	 *                                            - '!='
	 *                                            - '>'
	 *                                            - '>='
	 *                                            - '<'
	 *                                            - '<='
	 *                                            - 'LIKE'
	 *                                            - 'NOT LIKE'
	 *                                            - 'IN'
	 *                                            - 'NOT IN'
	 *                                            - 'BETWEEN'
	 *                                            - 'NOT BETWEEN'
	 *                                            - 'REGEXP'
	 *                                            - 'NOT REGEXP'
	 *                                            - 'RLIKE'
	 *                                            - 'EXISTS'
	 *                                            - 'NOT EXISTS'
	 *                                            Default is 'IN' when `$value` is an array, '=' otherwise.
	 *         @type string          $type        MySQL data type that the meta_value column will be CAST to for
	 *                                            comparisons. Accepts:
	 *                                            - 'NUMERIC'
	 *                                            - 'BINARY'
	 *                                            - 'CHAR'
	 *                                            - 'DATE'
	 *                                            - 'DATETIME'
	 *                                            - 'DECIMAL'
	 *                                            - 'SIGNED'
	 *                                            - 'TIME'
	 *                                            - 'UNSIGNED'
	 *                                            Default is 'CHAR'.
	 *     }
	 * }
	 */
	public function __construct( $meta_query = array() ) {
		if ( ! $meta_query ) {
			return;
		}

		if ( isset( $meta_query['relation'] ) && 'OR' === strtoupper( $meta_query['relation'] ) ) {
			$this->relation = 'OR';
		} else {
			$this->relation = 'AND';
		}

		$this->queries = $this->sanitize_query( $meta_query );
	}

	/**
	 * Ensures the 'meta_query' argument passed to the class constructor is well-formed.
	 *
	 * Eliminates empty items and ensures that a 'relation' is set.
	 *
	 * @since 4.1.0
	 *
	 * @param array $queries Array of query clauses.
	 * @return array Sanitized array of query clauses.
	 */
	public function sanitize_query( $queries ) {
		$clean_queries = array();

		if ( ! is_array( $queries ) ) {
			return $clean_queries;
		}

		foreach ( $queries as $key => $query ) {
			if ( 'relation' === $key ) {
				$relation = $query;

			} elseif ( ! is_array( $query ) ) {
				continue;

				// First-order clause.
			} elseif ( $this->is_first_order_clause( $query ) ) {
				if ( isset( $query['value'] ) && array() === $query['value'] ) {
					unset( $query['value'] );
				}

				$clean_queries[ $key ] = $query;

				// Otherwise, it's a nested query, so we recurse.
			} else {
				$cleaned_query = $this->sanitize_query( $query );

				if ( ! empty( $cleaned_query ) ) {
					$clean_queries[ $key ] = $cleaned_query;
				}
			}
		}

		if ( empty( $clean_queries ) ) {
			return $clean_queries;
		}

		// Sanitize the 'relation' key provided in the query.
		if ( isset( $relation ) && 'OR' === strtoupper( $relation ) ) {
			$clean_queries['relation'] = 'OR';
			$this->has_or_relation     = true;

			/*
			* If there is only a single clause, call the relation 'OR'.
			* This value will not actually be used to join clauses, but it
			* simplifies the logic around combining key-only queries.
			*/
		} elseif ( 1 === count( $clean_queries ) ) {
			$clean_queries['relation'] = 'OR';

			// Default to AND.
		} else {
			$clean_queries['relation'] = 'AND';
		}

		return $clean_queries;
	}

	/**
	 * Determines whether a query clause is first-order.
	 *
	 * A first-order meta query clause is one that has either a 'key' or
	 * a 'value' array key.
	 *
	 * @since 4.1.0
	 *
	 * @param array $query Meta query arguments.
	 * @return bool Whether the query clause is a first-order clause.
	 */
	protected function is_first_order_clause( $query ) {
		return isset( $query['key'] ) || isset( $query['value'] );
	}

	/**
	 * Constructs a meta query based on 'meta_*' query vars
	 *
	 * @since 3.2.0
	 *
	 * @param array $qv The query variables.
	 */
	public function parse_query_vars( $qv ) {
		$meta_query = array();

		/*
		 * For orderby=meta_value to work correctly, simple query needs to be
		 * first (so that its table join is against an unaliased meta table) and
		 * needs to be its own clause (so it doesn't interfere with the logic of
		 * the rest of the meta_query).
		 */
		$primary_meta_query = array();
		foreach ( array( 'key', 'compare', 'type', 'compare_key', 'type_key' ) as $key ) {
			if ( ! empty( $qv[ "meta_$key" ] ) ) {
				$primary_meta_query[ $key ] = $qv[ "meta_$key" ];
			}
		}

		// WP_Query sets 'meta_value' = '' by default.
		if ( isset( $qv['meta_value'] ) && '' !== $qv['meta_value'] && ( ! is_array( $qv['meta_value'] ) || $qv['meta_value'] ) ) {
			$primary_meta_query['value'] = $qv['meta_value'];
		}

		$existing_meta_query = isset( $qv['meta_query'] ) && is_array( $qv['meta_query'] ) ? $qv['meta_query'] : array();

		if ( ! empty( $primary_meta_query ) && ! empty( $existing_meta_query ) ) {
			$meta_query = array(
				'relation' => 'AND',
				$primary_meta_query,
				$existing_meta_query,
			);
		} elseif ( ! empty( $primary_meta_query ) ) {
			$meta_query = array(
				$primary_meta_query,
			);
		} elseif ( ! empty( $existing_meta_query ) ) {
			$meta_query = $existing_meta_query;
		}

		$this->__construct( $meta_query );
	}

	/**
	 * Returns the appropriate alias for the given meta type if applicable.
	 *
	 * @since 3.7.0
	 *
	 * @param string $type MySQL type to cast meta_value.
	 * @return string MySQL type.
	 */
	public function get_cast_for_type( $type = '' ) {
		if ( empty( $type ) ) {
			return 'CHAR';
		}

		/*
		 * Static cache eliminates repeated regex evaluation for the same type string.
		 * Common in loops where multiple clauses share identical type declarations
		 * (e.g., 'NUMERIC' or 'DECIMAL(10,2)').
		 *
		 * @since 7.0.0
		 */
		static $cast_cache = array();

		if ( isset( $cast_cache[ $type ] ) ) {
			return $cast_cache[ $type ];
		}

		$meta_type = strtoupper( $type );

		if ( ! preg_match( '/^(?:BINARY|CHAR|DATE|DATETIME|SIGNED|UNSIGNED|TIME|NUMERIC(?:\(\d+(?:,\s?\d+)?\))?|DECIMAL(?:\(\d+(?:,\s?\d+)?\))?)$/', $meta_type ) ) {
			$cast_cache[ $type ] = 'CHAR';
			return 'CHAR';
		}

		if ( 'NUMERIC' === $meta_type ) {
			$meta_type = 'SIGNED';
		}

		$cast_cache[ $type ] = $meta_type;
		return $meta_type;
	}

	/**
	 * Generates SQL clauses to be appended to a main query.
	 *
	 * @since 3.2.0
	 *
	 * @param string $type              Type of meta. Possible values include but are not limited
	 *                                  to 'post', 'comment', 'blog', 'term', and 'user'.
	 * @param string $primary_table     Database table where the object being filtered is stored (eg wp_users).
	 * @param string $primary_id_column ID column for the filtered object in $primary_table.
	 * @param object $context           Optional. The main query object that corresponds to the type, for
	 *                                  example a `WP_Query`, `WP_User_Query`, or `WP_Site_Query`.
	 *                                  Default null.
	 * @return string[]|false {
	 *     Array containing JOIN and WHERE SQL clauses to append to the main query,
	 *     or false if no table exists for the requested meta type.
	 *
	 *     @type string $join  SQL fragment to append to the main JOIN clause.
	 *     @type string $where SQL fragment to append to the main WHERE clause.
	 * }
	 */
	public function get_sql( $type, $primary_table, $primary_id_column, $context = null ) {
		$meta_table = _get_meta_table( $type );
		if ( ! $meta_table ) {
			return false;
		}

		$this->meta_table     = $meta_table;
		$this->meta_id_column = sanitize_key( $type . '_id' );

		$this->primary_table     = $primary_table;
		$this->primary_id_column = $primary_id_column;

		/*
		 * Static cache for identical meta query specifications within a request.
		 *
		 * REST API collection responses and template loops often execute multiple
		 * queries sharing the same meta_query structure. Caching the generated SQL
		 * (pre-filter) and internal state avoids redundant clause construction.
		 *
		 * @since 7.0.0
		 */
		static $sql_cache = array();

		$cache_key = $this->get_sql_cache_key( $type, $primary_table, $primary_id_column, $context );

		if ( isset( $sql_cache[ $cache_key ] ) ) {
			$cached              = $sql_cache[ $cache_key ];
			$sql                 = $cached['sql'];
			$this->table_aliases = $cached['table_aliases'];
			$this->clauses       = $cached['clauses'];
		} else {
			$this->table_aliases = array();

			/*
			 * Determine whether the EXISTS subquery optimization can be safely applied.
			 *
			 * The optimization replaces a JOIN + WHERE pattern with a correlated EXISTS
			 * subquery for simple single-clause meta queries (= or IN on key and value).
			 * It is disabled when:
			 *  - The query contains multiple first-order clauses or nested sub-queries.
			 *  - The context query orders by meta_value / meta_value_num (needs the JOIN alias).
			 *  - An OR relation is present.
			 *  - A filter is attached to 'meta_query_find_compatible_table_alias'.
			 *
			 * @since 7.0.0
			 */
			$this->use_exists_subquery = $this->should_use_exists_subquery( $context );

			$sql = $this->get_sql_clauses();

			/*
			 * If any JOINs are LEFT JOINs (as in the case of NOT EXISTS), then all JOINs should
			 * be LEFT. Otherwise posts with no metadata will be excluded from results.
			 */
			if ( str_contains( $sql['join'], 'LEFT JOIN' ) ) {
				$sql['join'] = str_replace( 'INNER JOIN', 'LEFT JOIN', $sql['join'] );
			}

			// Reset the flag after SQL generation.
			$this->use_exists_subquery = false;

			/*
			 * Bound cache to a reasonable size to prevent memory growth on long-running
			 * processes (e.g., WP-CLI bulk imports). 50 entries covers typical REST
			 * collection diversity without unbounded accumulation.
			 */
			if ( count( $sql_cache ) < 50 ) {
				$sql_cache[ $cache_key ] = array(
					'sql'           => $sql,
					'table_aliases' => $this->table_aliases,
					'clauses'       => $this->clauses,
				);
			}
		}

		/**
		 * Filters the meta query's generated SQL.
		 *
		 * @since 3.1.0
		 *
		 * @param string[] $sql               Array containing the query's JOIN and WHERE clauses.
		 * @param array    $queries           Array of meta queries.
		 * @param string   $type              Type of meta. Possible values include but are not limited
		 *                                    to 'post', 'comment', 'blog', 'term', and 'user'.
		 * @param string   $primary_table     Primary table.
		 * @param string   $primary_id_column Primary column ID.
		 * @param object   $context           The main query object that corresponds to the type, for
		 *                                    example a `WP_Query`, `WP_User_Query`, or `WP_Site_Query`.
		 */
		return apply_filters_ref_array( 'get_meta_sql', array( $sql, $this->queries, $type, $primary_table, $primary_id_column, $context ) );
	}

	/**
	 * Generates a cache key for get_sql() result caching.
	 *
	 * Combines the serialised query specification with the table context to
	 * produce a deterministic key. Uses md5 for speed — this is a runtime
	 * lookup key, not a cryptographic hash.
	 *
	 * @since 7.0.0
	 *
	 * @param string      $type              Meta type.
	 * @param string      $primary_table     Primary table name.
	 * @param string      $primary_id_column Primary ID column.
	 * @param object|null $context           Optional. The main query object. Used to
	 *                                       fingerprint orderby-related vars that affect
	 *                                       EXISTS vs JOIN SQL generation. Default null.
	 * @return string Cache key.
	 */
	private function get_sql_cache_key( $type, $primary_table, $primary_id_column, $context = null ) {
		/*
		 * The context determines whether the EXISTS subquery optimisation is
		 * used (via should_use_exists_subquery()). Different contexts — e.g.
		 * one query ordering by meta_value and another ordering by date —
		 * produce structurally different SQL (JOIN vs EXISTS). Including
		 * a context fingerprint in the key prevents a cached EXISTS result
		 * from being returned for a caller that needs a JOIN-based SQL.
		 *
		 * Only the orderby-related portion of the context is relevant; the
		 * full context object is not serialised to keep the key lightweight.
		 *
		 * @since 7.0.0
		 */
		$context_fingerprint = '';
		if ( null !== $context ) {
			if ( isset( $context->query_vars['orderby'] ) ) {
				$context_fingerprint .= '|ob:' . ( is_array( $context->query_vars['orderby'] )
					? serialize( $context->query_vars['orderby'] )
					: (string) $context->query_vars['orderby'] );
			}
			if ( isset( $context->query_vars['meta_key'] ) ) {
				$context_fingerprint .= '|mk:' . (string) $context->query_vars['meta_key'];
			}
		}

		return md5( serialize( $this->queries ) . '|' . $type . '|' . $primary_table . '|' . $primary_id_column . $context_fingerprint );
	}

	/**
	 * Determines whether the EXISTS subquery optimization is applicable.
	 *
	 * The EXISTS pattern replaces:
	 *   INNER JOIN meta_table ON (...) WHERE meta_key = 'x' AND meta_value = 'y'
	 * with:
	 *   WHERE EXISTS (SELECT 1 FROM meta_table WHERE id_col = outer.id AND meta_key = 'x' AND meta_value = 'y')
	 *
	 * This avoids adding a table to the FROM clause, allowing MySQL to
	 * short-circuit via an index-only lookup on (id_column, meta_key).
	 *
	 * Safety conditions checked:
	 *  1. Exactly one first-order clause (no nesting, no multi-clause).
	 *  2. The clause uses '=' key comparison with a scalar key.
	 *  3. The clause uses '=', 'IN', 'EXISTS' (treated as =), or no value (key-only).
	 *  4. No OR relation.
	 *  5. The context query does not order by meta_value / meta_value_num.
	 *  6. No active filter on 'meta_query_find_compatible_table_alias' that may
	 *     depend on JOIN aliases.
	 *
	 * @since 7.0.0
	 *
	 * @param object|null $context The main query object, or null.
	 * @return bool True if the EXISTS optimization should be used.
	 */
	private function should_use_exists_subquery( $context ) {
		// Must have queries to optimize.
		if ( empty( $this->queries ) ) {
			return false;
		}

		/*
		 * Context is required to verify ordering safety. Without it we cannot
		 * determine whether the caller will use the meta JOIN alias in ORDER BY
		 * (e.g., WP_Term_Query calls get_sql() without context but may still
		 * order by meta_value via parse_orderby_meta()). Conservative: bail out.
		 */
		if ( null === $context || ! isset( $context->query_vars ) ) {
			return false;
		}

		// Count first-order clauses — must be exactly one.
		$first_order_count = 0;
		$first_clause      = null;

		foreach ( $this->queries as $key => $query ) {
			if ( 'relation' === $key ) {
				continue;
			}
			if ( ! is_array( $query ) ) {
				continue;
			}
			if ( $this->is_first_order_clause( $query ) ) {
				++$first_order_count;
				$first_clause = $query;
			} else {
				// Nested sub-query present — bail out.
				return false;
			}
		}

		if ( 1 !== $first_order_count || null === $first_clause ) {
			return false;
		}

		// Key must be a scalar string with simple '=' comparison.
		if ( ! isset( $first_clause['key'] ) || is_array( $first_clause['key'] ) ) {
			return false;
		}

		if ( isset( $first_clause['compare_key'] ) && '=' !== strtoupper( $first_clause['compare_key'] ) && 'EXISTS' !== strtoupper( $first_clause['compare_key'] ) ) {
			return false;
		}

		// Value comparison must be simple: '=', 'IN', 'EXISTS', or absent (key-only check).
		if ( isset( $first_clause['compare'] ) ) {
			$compare = strtoupper( $first_clause['compare'] );
			if ( ! in_array( $compare, array( '=', 'IN', 'EXISTS' ), true ) ) {
				return false;
			}
		}

		// No OR relation.
		if ( $this->has_or_relation ) {
			return false;
		}

		// Check context for meta-based ordering which requires the JOIN alias.
		if ( null !== $context && isset( $context->query_vars['orderby'] ) ) {
			$orderby = $context->query_vars['orderby'];

			/*
			 * Build a blocklist of orderby values that reference the meta table.
			 * Multiple callers (WP_Query, WP_Comment_Query, WP_User_Query, etc.)
			 * generate ORDER BY clauses referencing meta_value via different patterns:
			 *  - 'meta_value' / 'meta_value_num' keywords
			 *  - The literal meta_key value (WP_Comment_Query matches orderby == meta_key)
			 *  - Named clause keys (WP_Query matches orderby against clause key names)
			 */
			$meta_orderby_blocklist = array( 'meta_value', 'meta_value_num' );

			if ( ! empty( $context->query_vars['meta_key'] ) ) {
				$meta_orderby_blocklist[] = $context->query_vars['meta_key'];
			}

			foreach ( $this->queries as $key => $query ) {
				if ( 'relation' !== $key && is_string( $key ) ) {
					$meta_orderby_blocklist[] = $key;
				}
			}

			/*
			 * Normalize orderby into a flat list for checking. Orderby can be:
			 *  - A string: 'meta_value', 'date', 'foo_clause', etc.
			 *  - An associative array: array( 'foo_clause' => 'ASC', 'date' => 'DESC' )
			 *  - A numeric array: array( 'meta_value', 'date' ) — values are the orderby.
			 */
			$orderby_check_values = array();
			if ( is_string( $orderby ) ) {
				$orderby_check_values[] = $orderby;
			} elseif ( is_array( $orderby ) ) {
				foreach ( $orderby as $ob_key => $ob_val ) {
					$orderby_check_values[] = is_int( $ob_key ) ? $ob_val : $ob_key;
				}
			}

			foreach ( $orderby_check_values as $ob ) {
				if ( in_array( $ob, $meta_orderby_blocklist, true ) ) {
					return false;
				}
			}
		}

		/*
		 * If a filter is registered on 'meta_query_find_compatible_table_alias', external
		 * code may rely on JOIN aliases existing. Fall back to the standard JOIN path.
		 */
		if ( has_filter( 'meta_query_find_compatible_table_alias' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Generates SQL clauses to be appended to a main query.
	 *
	 * Called by the public WP_Meta_Query::get_sql(), this method is abstracted
	 * out to maintain parity with the other Query classes.
	 *
	 * @since 4.1.0
	 *
	 * @return string[] {
	 *     Array containing JOIN and WHERE SQL clauses to append to the main query.
	 *
	 *     @type string $join  SQL fragment to append to the main JOIN clause.
	 *     @type string $where SQL fragment to append to the main WHERE clause.
	 * }
	 */
	protected function get_sql_clauses() {
		/*
		 * $queries are passed by reference to get_sql_for_query() for recursion.
		 * To keep $this->queries unaltered, pass a copy.
		 */
		$queries = $this->queries;
		$sql     = $this->get_sql_for_query( $queries );

		if ( ! empty( $sql['where'] ) ) {
			$sql['where'] = ' AND ' . $sql['where'];
		}

		return $sql;
	}

	/**
	 * Generates SQL clauses for a single query array.
	 *
	 * If nested subqueries are found, this method recurses the tree to
	 * produce the properly nested SQL.
	 *
	 * @since 4.1.0
	 *
	 * @param array $query Query to parse (passed by reference).
	 * @param int   $depth Optional. Number of tree levels deep we currently are.
	 *                     Used to calculate indentation. Default 0.
	 * @return string[] {
	 *     Array containing JOIN and WHERE SQL clauses to append to a single query array.
	 *
	 *     @type string $join  SQL fragment to append to the main JOIN clause.
	 *     @type string $where SQL fragment to append to the main WHERE clause.
	 * }
	 */
	protected function get_sql_for_query( &$query, $depth = 0 ) {
		$sql_chunks = array(
			'join'  => array(),
			'where' => array(),
		);

		$sql = array(
			'join'  => '',
			'where' => '',
		);

		$indent = '';
		for ( $i = 0; $i < $depth; $i++ ) {
			$indent .= '  ';
		}

		foreach ( $query as $key => &$clause ) {
			if ( 'relation' === $key ) {
				$relation = $query['relation'];
			} elseif ( is_array( $clause ) ) {

				// This is a first-order clause.
				if ( $this->is_first_order_clause( $clause ) ) {
					$clause_sql = $this->get_sql_for_clause( $clause, $query, $key );

					$where_count = count( $clause_sql['where'] );
					if ( ! $where_count ) {
						$sql_chunks['where'][] = '';
					} elseif ( 1 === $where_count ) {
						$sql_chunks['where'][] = $clause_sql['where'][0];
					} else {
						$sql_chunks['where'][] = '( ' . implode( ' AND ', $clause_sql['where'] ) . ' )';
					}

					$sql_chunks['join'] = array_merge( $sql_chunks['join'], $clause_sql['join'] );
					// This is a subquery, so we recurse.
				} else {
					$clause_sql = $this->get_sql_for_query( $clause, $depth + 1 );

					$sql_chunks['where'][] = $clause_sql['where'];
					$sql_chunks['join'][]  = $clause_sql['join'];
				}
			}
		}

		// Filter to remove empties.
		$sql_chunks['join']  = array_filter( $sql_chunks['join'] );
		$sql_chunks['where'] = array_filter( $sql_chunks['where'] );

		if ( empty( $relation ) ) {
			$relation = 'AND';
		}

		// Filter duplicate JOIN clauses and combine into a single string.
		if ( ! empty( $sql_chunks['join'] ) ) {
			$sql['join'] = implode( ' ', array_unique( $sql_chunks['join'] ) );
		}

		// Generate a single WHERE clause with proper brackets and indentation.
		if ( ! empty( $sql_chunks['where'] ) ) {
			$sql['where'] = '( ' . "\n  " . $indent . implode( ' ' . "\n  " . $indent . $relation . ' ' . "\n  " . $indent, $sql_chunks['where'] ) . "\n" . $indent . ')';
		}

		return $sql;
	}

	/**
	 * Generates SQL JOIN and WHERE clauses for a first-order query clause.
	 *
	 * "First-order" means that it's an array with a 'key' or 'value'.
	 *
	 * @since 4.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param array  $clause       Query clause (passed by reference).
	 * @param array  $parent_query Parent query array.
	 * @param string $clause_key   Optional. The array key used to name the clause in the original `$meta_query`
	 *                             parameters. If not provided, a key will be generated automatically.
	 *                             Default empty string.
	 * @return array {
	 *     Array containing JOIN and WHERE SQL clauses to append to a first-order query.
	 *
	 *     @type string[] $join  Array of SQL fragments to append to the main JOIN clause.
	 *     @type string[] $where Array of SQL fragments to append to the main WHERE clause.
	 * }
	 */
	public function get_sql_for_clause( &$clause, $parent_query, $clause_key = '' ) {
		global $wpdb;

		$sql_chunks = array(
			'where' => array(),
			'join'  => array(),
		);

		if ( isset( $clause['compare'] ) ) {
			$clause['compare'] = strtoupper( $clause['compare'] );
		} else {
			$clause['compare'] = isset( $clause['value'] ) && is_array( $clause['value'] ) ? 'IN' : '=';
		}

		$non_numeric_operators = array(
			'=',
			'!=',
			'LIKE',
			'NOT LIKE',
			'IN',
			'NOT IN',
			'EXISTS',
			'NOT EXISTS',
			'RLIKE',
			'REGEXP',
			'NOT REGEXP',
		);

		$numeric_operators = array(
			'>',
			'>=',
			'<',
			'<=',
			'BETWEEN',
			'NOT BETWEEN',
		);

		if ( ! in_array( $clause['compare'], $non_numeric_operators, true ) && ! in_array( $clause['compare'], $numeric_operators, true ) ) {
			$clause['compare'] = '=';
		}

		if ( isset( $clause['compare_key'] ) ) {
			$clause['compare_key'] = strtoupper( $clause['compare_key'] );
		} else {
			$clause['compare_key'] = isset( $clause['key'] ) && is_array( $clause['key'] ) ? 'IN' : '=';
		}

		if ( ! in_array( $clause['compare_key'], $non_numeric_operators, true ) ) {
			$clause['compare_key'] = '=';
		}

		$meta_compare     = $clause['compare'];
		$meta_compare_key = $clause['compare_key'];

		// Determine the data type early — needed by both JOIN and EXISTS paths.
		$_meta_type     = $clause['type'] ?? '';
		$meta_type      = $this->get_cast_for_type( $_meta_type );
		$clause['cast'] = $meta_type;

		/*
		 * EXISTS subquery fast path for simple single-clause meta queries.
		 *
		 * When enabled, replaces the standard JOIN + WHERE pattern:
		 *   INNER JOIN meta_table ON (...) WHERE meta_key = 'x' AND meta_value = 'y'
		 * with a correlated subquery:
		 *   WHERE EXISTS (SELECT 1 FROM meta_table WHERE id_col = outer.id AND meta_key = 'x' AND meta_value = 'y')
		 *
		 * Benefits: eliminates a table from the FROM clause, allows MySQL to use an
		 * index-only probe on (id_column, meta_key) and short-circuit after the first match.
		 *
		 * Only applied for simple equality / IN comparisons on CHAR-typed values.
		 * The should_use_exists_subquery() gate has already verified safety.
		 *
		 * @since 7.0.0
		 */
		if ( $this->use_exists_subquery
			&& 'CHAR' === $meta_type
			&& in_array( $meta_compare, array( '=', 'IN', 'EXISTS' ), true )
			&& in_array( $meta_compare_key, array( '=', 'EXISTS' ), true )
			&& array_key_exists( 'key', $clause )
			&& 'NOT EXISTS' !== $meta_compare
		) {
			return $this->get_sql_for_clause_exists( $clause, $parent_query, $clause_key, $meta_compare, $meta_type );
		}

		// First build the JOIN clause, if one is required.
		$join = '';

		// We prefer to avoid joins if possible. Look for an existing join compatible with this clause.
		$alias = $this->find_compatible_table_alias( $clause, $parent_query );
		if ( false === $alias ) {
			$i     = count( $this->table_aliases );
			$alias = $i ? 'mt' . $i : $this->meta_table;

			// JOIN clauses for NOT EXISTS have their own syntax.
			if ( 'NOT EXISTS' === $meta_compare ) {
				$join .= " LEFT JOIN $this->meta_table";
				$join .= $i ? " AS $alias" : '';

				if ( 'LIKE' === $meta_compare_key ) {
					$join .= $wpdb->prepare( " ON ( $this->primary_table.$this->primary_id_column = $alias.$this->meta_id_column AND $alias.meta_key LIKE %s )", '%' . $wpdb->esc_like( $clause['key'] ) . '%' );
				} else {
					$join .= $wpdb->prepare( " ON ( $this->primary_table.$this->primary_id_column = $alias.$this->meta_id_column AND $alias.meta_key = %s )", $clause['key'] );
				}

				// All other JOIN clauses.
			} else {
				$join .= " INNER JOIN $this->meta_table";
				$join .= $i ? " AS $alias" : '';
				$join .= " ON ( $this->primary_table.$this->primary_id_column = $alias.$this->meta_id_column )";
			}

			$this->table_aliases[] = $alias;
			$sql_chunks['join'][]  = $join;
		}

		// Save the alias to this clause, for future siblings to find.
		$clause['alias'] = $alias;

		// Fallback for clause keys is the table alias. Key must be a string.
		if ( is_int( $clause_key ) || ! $clause_key ) {
			$clause_key = $clause['alias'];
		}

		// Ensure unique clause keys, so none are overwritten.
		$iterator        = 1;
		$clause_key_base = $clause_key;
		while ( isset( $this->clauses[ $clause_key ] ) ) {
			$clause_key = $clause_key_base . '-' . $iterator;
			++$iterator;
		}

		// Store the clause in our flat array.
		$this->clauses[ $clause_key ] =& $clause;

		// Next, build the WHERE clause.

		// meta_key.
		if ( array_key_exists( 'key', $clause ) ) {
			if ( 'NOT EXISTS' === $meta_compare ) {
				$sql_chunks['where'][] = $alias . '.' . $this->meta_id_column . ' IS NULL';
			} else {
				/**
				 * In joined clauses negative operators have to be nested into a
				 * NOT EXISTS clause and flipped, to avoid returning records with
				 * matching post IDs but different meta keys. Here we prepare the
				 * nested clause.
				 */
				if ( in_array( $meta_compare_key, array( '!=', 'NOT IN', 'NOT LIKE', 'NOT EXISTS', 'NOT REGEXP' ), true ) ) {
					// Negative clauses may be reused.
					$i                     = count( $this->table_aliases );
					$subquery_alias        = $i ? 'mt' . $i : $this->meta_table;
					$this->table_aliases[] = $subquery_alias;

					$meta_compare_string_start  = 'NOT EXISTS (';
					$meta_compare_string_start .= "SELECT 1 FROM $wpdb->postmeta $subquery_alias ";
					$meta_compare_string_start .= "WHERE $subquery_alias.post_ID = $alias.post_ID ";
					$meta_compare_string_end    = 'LIMIT 1';
					$meta_compare_string_end   .= ')';
				}

				switch ( $meta_compare_key ) {
					case '=':
					case 'EXISTS':
						$where = $wpdb->prepare( "$alias.meta_key = %s", trim( $clause['key'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						break;
					case 'LIKE':
						$meta_compare_value = '%' . $wpdb->esc_like( trim( $clause['key'] ) ) . '%';
						$where              = $wpdb->prepare( "$alias.meta_key LIKE %s", $meta_compare_value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						break;
					case 'IN':
						$meta_compare_string = "$alias.meta_key IN (" . substr( str_repeat( ',%s', count( $clause['key'] ) ), 1 ) . ')';
						$where               = $wpdb->prepare( $meta_compare_string, $clause['key'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						break;
					case 'RLIKE':
					case 'REGEXP':
						$operator = $meta_compare_key;
						if ( isset( $clause['type_key'] ) && 'BINARY' === strtoupper( $clause['type_key'] ) ) {
							$cast     = 'BINARY';
							$meta_key = "CAST($alias.meta_key AS BINARY)";
						} else {
							$cast     = '';
							$meta_key = "$alias.meta_key";
						}
						$where = $wpdb->prepare( "$meta_key $operator $cast %s", trim( $clause['key'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						break;

					case '!=':
					case 'NOT EXISTS':
						$meta_compare_string = $meta_compare_string_start . "AND $subquery_alias.meta_key = %s " . $meta_compare_string_end;
						$where               = $wpdb->prepare( $meta_compare_string, $clause['key'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						break;
					case 'NOT LIKE':
						$meta_compare_string = $meta_compare_string_start . "AND $subquery_alias.meta_key LIKE %s " . $meta_compare_string_end;

						$meta_compare_value = '%' . $wpdb->esc_like( trim( $clause['key'] ) ) . '%';
						$where              = $wpdb->prepare( $meta_compare_string, $meta_compare_value ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						break;
					case 'NOT IN':
						$array_subclause     = '(' . substr( str_repeat( ',%s', count( $clause['key'] ) ), 1 ) . ') ';
						$meta_compare_string = $meta_compare_string_start . "AND $subquery_alias.meta_key IN " . $array_subclause . $meta_compare_string_end;
						$where               = $wpdb->prepare( $meta_compare_string, $clause['key'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						break;
					case 'NOT REGEXP':
						$operator = $meta_compare_key;
						if ( isset( $clause['type_key'] ) && 'BINARY' === strtoupper( $clause['type_key'] ) ) {
							$cast     = 'BINARY';
							$meta_key = "CAST($subquery_alias.meta_key AS BINARY)";
						} else {
							$cast     = '';
							$meta_key = "$subquery_alias.meta_key";
						}

						$meta_compare_string = $meta_compare_string_start . "AND $meta_key REGEXP $cast %s " . $meta_compare_string_end;
						$where               = $wpdb->prepare( $meta_compare_string, $clause['key'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						break;
				}

				$sql_chunks['where'][] = $where;
			}
		}

		// meta_value.
		if ( array_key_exists( 'value', $clause ) ) {
			$meta_value = $clause['value'];

			if ( in_array( $meta_compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ), true ) ) {
				if ( ! is_array( $meta_value ) ) {
					$meta_value = preg_split( '/[,\s]+/', $meta_value );
				}
			} elseif ( is_string( $meta_value ) ) {
				$meta_value = trim( $meta_value );
			}

			switch ( $meta_compare ) {
				case 'IN':
				case 'NOT IN':
					$meta_compare_string = '(' . substr( str_repeat( ',%s', count( $meta_value ) ), 1 ) . ')';
					$where               = $wpdb->prepare( $meta_compare_string, $meta_value );
					break;

				case 'BETWEEN':
				case 'NOT BETWEEN':
					$where = $wpdb->prepare( '%s AND %s', $meta_value[0], $meta_value[1] );
					break;

				case 'LIKE':
				case 'NOT LIKE':
					$meta_value = '%' . $wpdb->esc_like( $meta_value ) . '%';
					$where      = $wpdb->prepare( '%s', $meta_value );
					break;

				// EXISTS with a value is interpreted as '='.
				case 'EXISTS':
					$meta_compare = '=';
					$where        = $wpdb->prepare( '%s', $meta_value );
					break;

				// 'value' is ignored for NOT EXISTS.
				case 'NOT EXISTS':
					$where = '';
					break;

				default:
					$where = $wpdb->prepare( '%s', $meta_value );
					break;

			}

			if ( $where ) {
				if ( 'CHAR' === $meta_type ) {
					$sql_chunks['where'][] = "$alias.meta_value {$meta_compare} {$where}";
				} else {
					$sql_chunks['where'][] = "CAST($alias.meta_value AS {$meta_type}) {$meta_compare} {$where}";
				}
			}
		}

		/*
		 * Multiple WHERE clauses (for meta_key and meta_value) should
		 * be joined in parentheses.
		 */
		if ( 1 < count( $sql_chunks['where'] ) ) {
			$sql_chunks['where'] = array( '( ' . implode( ' AND ', $sql_chunks['where'] ) . ' )' );
		}

		return $sql_chunks;
	}

	/**
	 * Generates an EXISTS subquery for a single-clause meta query.
	 *
	 * Produces a correlated subquery of the form:
	 *   EXISTS (SELECT 1 FROM meta_table
	 *           WHERE meta_table.id_col = primary_table.id_col
	 *             AND meta_table.meta_key = %s
	 *             [AND meta_table.meta_value {compare} {value}])
	 *
	 * The result is returned in the same sql_chunks format as get_sql_for_clause()
	 * so the caller processes it identically.
	 *
	 * @since 7.0.0
	 *
	 * @param array  $clause       Query clause (passed by reference from get_sql_for_clause).
	 * @param array  $parent_query Parent query array.
	 * @param string $clause_key   Clause key.
	 * @param string $meta_compare Value comparison operator.
	 * @param string $meta_type    Cast type (always CHAR when this method is reached).
	 * @return array SQL chunks with 'join' (empty) and 'where' arrays.
	 */
	private function get_sql_for_clause_exists( &$clause, $parent_query, $clause_key, $meta_compare, $meta_type ) {
		global $wpdb;

		$sql_chunks = array(
			'where' => array(),
			'join'  => array(),
		);

		/*
		 * Set alias for clause metadata compatibility.
		 * The alias is recorded so that get_clauses() returns consistent data,
		 * but no JOIN is created — the meta table is only referenced inside
		 * the correlated EXISTS subquery.
		 */
		$i     = count( $this->table_aliases );
		$alias = $i ? 'mt' . $i : $this->meta_table;

		$this->table_aliases[] = $alias;
		$clause['alias']       = $alias;

		// Clause key handling — same logic as the standard path.
		if ( is_int( $clause_key ) || ! $clause_key ) {
			$clause_key = $clause['alias'];
		}

		$iterator        = 1;
		$clause_key_base = $clause_key;
		while ( isset( $this->clauses[ $clause_key ] ) ) {
			$clause_key = $clause_key_base . '-' . $iterator;
			++$iterator;
		}

		$this->clauses[ $clause_key ] =& $clause;

		// Build the EXISTS subquery.
		$exists_conditions = array();

		// Join condition: correlate inner table to outer primary key.
		$exists_conditions[] = "$this->meta_table.$this->meta_id_column = $this->primary_table.$this->primary_id_column";

		// meta_key condition.
		$exists_conditions[] = $wpdb->prepare( "$this->meta_table.meta_key = %s", trim( $clause['key'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// meta_value condition (optional — key-only queries are valid).
		if ( array_key_exists( 'value', $clause ) ) {
			$meta_value    = $clause['value'];
			$eff_compare   = $meta_compare;

			// EXISTS with a value is interpreted as '='.
			if ( 'EXISTS' === $eff_compare ) {
				$eff_compare = '=';
			}

			if ( 'IN' === $eff_compare ) {
				if ( ! is_array( $meta_value ) ) {
					$meta_value = preg_split( '/[,\s]+/', $meta_value );
				}
				$placeholders      = '(' . substr( str_repeat( ',%s', count( $meta_value ) ), 1 ) . ')';
				$exists_conditions[] = $wpdb->prepare( "$this->meta_table.meta_value IN $placeholders", $meta_value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			} else {
				// '=' comparison.
				if ( is_string( $meta_value ) ) {
					$meta_value = trim( $meta_value );
				}
				$exists_conditions[] = $wpdb->prepare( "$this->meta_table.meta_value = %s", $meta_value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		$conditions_sql = implode( ' AND ', $exists_conditions );
		$sql_chunks['where'][] = "EXISTS (SELECT 1 FROM $this->meta_table WHERE $conditions_sql)"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $sql_chunks;
	}

	/**
	 * Gets a flattened list of sanitized meta clauses.
	 *
	 * This array should be used for clause lookup, as when the table alias and CAST type must be determined for
	 * a value of 'orderby' corresponding to a meta clause.
	 *
	 * @since 4.2.0
	 *
	 * @return array Meta clauses.
	 */
	public function get_clauses() {
		return $this->clauses;
	}

	/**
	 * Identifies an existing table alias that is compatible with the current
	 * query clause.
	 *
	 * We avoid unnecessary table joins by allowing each clause to look for
	 * an existing table alias that is compatible with the query that it
	 * needs to perform.
	 *
	 * An existing alias is compatible if (a) it is a sibling of `$clause`
	 * (ie, it's under the scope of the same relation), and (b) the combination
	 * of operator and relation between the clauses allows for a shared table join.
	 * In the case of WP_Meta_Query, this only applies to 'IN' clauses that are
	 * connected by the relation 'OR'.
	 *
	 * @since 4.1.0
	 *
	 * @param array $clause       Query clause.
	 * @param array $parent_query Parent query of $clause.
	 * @return string|false Table alias if found, otherwise false.
	 */
	protected function find_compatible_table_alias( $clause, $parent_query ) {
		$alias = false;

		foreach ( $parent_query as $sibling ) {
			// If the sibling has no alias yet, there's nothing to check.
			if ( empty( $sibling['alias'] ) ) {
				continue;
			}

			// We're only interested in siblings that are first-order clauses.
			if ( ! is_array( $sibling ) || ! $this->is_first_order_clause( $sibling ) ) {
				continue;
			}

			$compatible_compares = array();

			// Clauses connected by OR can share joins as long as they have "positive" operators.
			if ( 'OR' === $parent_query['relation'] ) {
				$compatible_compares = array( '=', 'IN', 'BETWEEN', 'LIKE', 'REGEXP', 'RLIKE', '>', '>=', '<', '<=' );

				// Clauses joined by AND with "negative" operators share a join only if they also share a key.
			} elseif ( isset( $sibling['key'] ) && isset( $clause['key'] ) && $sibling['key'] === $clause['key'] ) {
				$compatible_compares = array( '!=', 'NOT IN', 'NOT LIKE' );
			}

			$clause_compare  = strtoupper( $clause['compare'] );
			$sibling_compare = strtoupper( $sibling['compare'] );
			if ( in_array( $clause_compare, $compatible_compares, true ) && in_array( $sibling_compare, $compatible_compares, true ) ) {
				$alias = preg_replace( '/\W/', '_', $sibling['alias'] );
				break;
			}
		}

		/**
		 * Filters the table alias identified as compatible with the current clause.
		 *
		 * @since 4.1.0
		 *
		 * @param string|false  $alias        Table alias, or false if none was found.
		 * @param array         $clause       First-order query clause.
		 * @param array         $parent_query Parent of $clause.
		 * @param WP_Meta_Query $query        WP_Meta_Query object.
		 */
		return apply_filters( 'meta_query_find_compatible_table_alias', $alias, $clause, $parent_query, $this );
	}

	/**
	 * Checks whether the current query has any OR relations.
	 *
	 * In some cases, the presence of an OR relation somewhere in the query will require
	 * the use of a `DISTINCT` or `GROUP BY` keyword in the `SELECT` clause. The current
	 * method can be used in these cases to determine whether such a clause is necessary.
	 *
	 * @since 4.3.0
	 *
	 * @return bool True if the query contains any `OR` relations, otherwise false.
	 */
	public function has_or_relation() {
		return $this->has_or_relation;
	}
}
