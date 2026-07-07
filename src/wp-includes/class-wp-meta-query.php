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
	 * Orderby references derived from the primary query's context.
	 *
	 * Computed once per get_sql() call from the primary query object's
	 * `orderby` query variable. It records which meta clauses supply a column
	 * to the outer query's `ORDER BY`, so the EXISTS-subquery optimization in
	 * WP_Meta_Query::is_meta_value_exists_clause() can preserve the table JOIN
	 * for any clause whose meta value is needed for sorting (an EXISTS subquery
	 * exposes no column to the outer query and therefore cannot be sorted on).
	 *
	 * Structure:
	 *
	 *  - 'known'  bool     Whether a usable orderby context was available. When
	 *                      false, the value-referencing checks are skipped and
	 *                      the JOIN is preserved (conservative default).
	 *  - 'value'  bool     Whether the outer orderby references 'meta_value' or
	 *                      'meta_value_num', which resolve to the primary
	 *                      (first) meta clause's alias.
	 *  - 'keys'   array    Set of orderby tokens (token => true), used to detect
	 *                      references to a clause by its meta key or by a named
	 *                      clause key.
	 *
	 * @since 7.0.0
	 * @var array
	 */
	protected $meta_value_orderby_refs = array(
		'known' => false,
		'value' => false,
		'keys'  => array(),
	);

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

		$meta_type = strtoupper( $type );

		if ( ! preg_match( '/^(?:BINARY|CHAR|DATE|DATETIME|SIGNED|UNSIGNED|TIME|NUMERIC(?:\(\d+(?:,\s?\d+)?\))?|DECIMAL(?:\(\d+(?:,\s?\d+)?\))?)$/', $meta_type ) ) {
			return 'CHAR';
		}

		if ( 'NUMERIC' === $meta_type ) {
			$meta_type = 'SIGNED';
		}

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

		$this->table_aliases = array();

		$this->meta_table     = $meta_table;
		$this->meta_id_column = sanitize_key( $type . '_id' );

		$this->primary_table     = $primary_table;
		$this->primary_id_column = $primary_id_column;

		/*
		 * Determine which meta clauses feed the outer query's ORDER BY before
		 * building the clauses, so the EXISTS-subquery optimization can keep the
		 * JOIN for any clause whose meta value is needed for sorting. An EXISTS
		 * subquery exposes no column to the outer query and therefore cannot be
		 * sorted on.
		 */
		$this->meta_value_orderby_refs = $this->get_orderby_clause_references( $context );

		$sql = $this->get_sql_clauses();

		/*
		 * If any JOINs are LEFT JOINs (as in the case of NOT EXISTS), then all JOINs should
		 * be LEFT. Otherwise posts with no metadata will be excluded from results.
		 */
		if ( str_contains( $sql['join'], 'LEFT JOIN' ) ) {
			$sql['join'] = str_replace( 'INNER JOIN', 'LEFT JOIN', $sql['join'] );
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

		// First build the JOIN clause, if one is required.
		$join = '';

		/*
		 * Whether this clause is expressed as a correlated EXISTS subquery in the
		 * WHERE clause instead of adding a table JOIN. This is set to true below
		 * only for clauses that receive their own dedicated alias (the
		 * `false === $alias` branch); clauses that share an existing alias keep
		 * their JOIN. Initialized here so it is always defined for the WHERE
		 * clause building further down.
		 */
		$exists_subquery = false;

		// We prefer to avoid joins if possible. Look for an existing join compatible with this clause.
		$alias = $this->find_compatible_table_alias( $clause, $parent_query );
		if ( false === $alias ) {
			$i     = count( $this->table_aliases );
			$alias = $i ? 'mt' . $i : $this->meta_table;

			/*
			 * "Negative" value comparisons ('!=', 'NOT IN', 'NOT LIKE',
			 * 'NOT BETWEEN') only test whether a matching meta row exists; they
			 * never need to expose the meta value to the SELECT or ORDER BY of the
			 * outer query. Such a clause can therefore be rewritten as a correlated
			 * EXISTS ( SELECT 1 FROM ... ) subquery in the WHERE clause below
			 * instead of an INNER JOIN, returning exactly the same set of primary
			 * rows while avoiding the JOIN cost. See
			 * WP_Meta_Query::is_meta_value_exists_clause() for the full set of
			 * safety conditions, which preserve the shared-JOIN semantics that
			 * same-key negative clauses joined by AND rely on.
			 */
			$exists_subquery = $this->is_meta_value_exists_clause( $clause, $parent_query, $meta_compare, $meta_compare_key, 0 === $i, $clause_key );

			// JOIN clauses for NOT EXISTS have their own syntax.
			if ( ! $exists_subquery && 'NOT EXISTS' === $meta_compare ) {
				$join .= " LEFT JOIN $this->meta_table";
				$join .= $i ? " AS $alias" : '';

				if ( 'LIKE' === $meta_compare_key ) {
					$join .= $wpdb->prepare( " ON ( $this->primary_table.$this->primary_id_column = $alias.$this->meta_id_column AND $alias.meta_key LIKE %s )", '%' . $wpdb->esc_like( $clause['key'] ) . '%' );
				} else {
					$join .= $wpdb->prepare( " ON ( $this->primary_table.$this->primary_id_column = $alias.$this->meta_id_column AND $alias.meta_key = %s )", $clause['key'] );
				}

				// All other JOIN clauses.
			} elseif ( ! $exists_subquery ) {
				$join .= " INNER JOIN $this->meta_table";
				$join .= $i ? " AS $alias" : '';
				$join .= " ON ( $this->primary_table.$this->primary_id_column = $alias.$this->meta_id_column )";
			}

			$this->table_aliases[] = $alias;

			/*
			 * Convertible clauses ($exists_subquery) build no JOIN, only a WHERE
			 * fragment; append the JOIN only when one was actually generated.
			 */
			if ( '' !== $join ) {
				$sql_chunks['join'][] = $join;
			}
		}

		// Save the alias to this clause, for future siblings to find.
		$clause['alias'] = $alias;

		// Determine the data type.
		$_meta_type     = $clause['type'] ?? '';
		$meta_type      = $this->get_cast_for_type( $_meta_type );
		$clause['cast'] = $meta_type;

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

		/*
		 * For clauses rewritten as an EXISTS subquery ($exists_subquery), the
		 * "meta_key = ..." condition is captured here instead of being emitted as
		 * a separate WHERE fragment, so it can be folded into the subquery next to
		 * the meta_value condition below.
		 */
		$exists_key_condition = '';

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

				/*
				 * Fold the meta_key condition into the pending EXISTS subquery for
				 * convertible clauses; otherwise emit it as its own WHERE fragment.
				 */
				if ( $exists_subquery ) {
					$exists_key_condition = $where;
				} else {
					$sql_chunks['where'][] = $where;
				}
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
					$meta_value_condition = "$alias.meta_value {$meta_compare} {$where}";
				} else {
					$meta_value_condition = "CAST($alias.meta_value AS {$meta_type}) {$meta_compare} {$where}";
				}

				if ( $exists_subquery ) {
					/*
					 * Rewrite this clause as a correlated EXISTS subquery. The
					 * reserved $alias is reused as the subquery's own table alias
					 * (no outer JOIN uses it), and the meta_key and "negative"
					 * meta_value conditions are folded into the subquery so that a
					 * single matching meta row is required - exactly as the
					 * equivalent INNER JOIN would demand, yielding an identical set
					 * of primary rows.
					 */
					if ( $alias === $this->meta_table ) {
						$exists_from = $this->meta_table;
					} else {
						$exists_from = "$this->meta_table AS $alias";
					}

					$sql_chunks['where'][] = "EXISTS ( SELECT 1 FROM $exists_from WHERE $this->primary_table.$this->primary_id_column = $alias.$this->meta_id_column AND $exists_key_condition AND $meta_value_condition )";
				} else {
					$sql_chunks['where'][] = $meta_value_condition;
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
	 * Collects the outer query's orderby references so the EXISTS-subquery
	 * optimization can preserve the JOIN for any meta clause whose value is
	 * needed for sorting.
	 *
	 * WP_Query::parse_orderby() (and the analogous logic in other primary query
	 * classes) resolves an 'orderby' of 'meta_value' or 'meta_value_num' to the
	 * primary (first) meta clause's alias, resolves an 'orderby' equal to the
	 * primary clause's meta key to that same alias, and resolves an 'orderby'
	 * equal to a named clause key to that named clause's alias. In every one of
	 * those cases a meta column is emitted in the ORDER BY, which requires the
	 * clause to keep a real table JOIN, because a correlated EXISTS subquery
	 * exposes no column to sort on. This method inspects the primary query
	 * object's 'orderby' query variable and records those references for
	 * WP_Meta_Query::is_meta_value_exists_clause().
	 *
	 * When no usable context is available (for example the context is null or a
	 * caller that is not a primary query object, as in isolated unit tests), the
	 * 'known' flag is left false so callers keep the JOIN unconditionally, which
	 * preserves the pre-optimization SQL byte-for-byte for those callers.
	 *
	 * @since 7.0.0
	 *
	 * @param object|null $context The primary query object that corresponds to the
	 *                             meta type, for example a WP_Query, WP_User_Query,
	 *                             or WP_Comment_Query. May be null.
	 * @return array {
	 *     References derived from the context's 'orderby'.
	 *
	 *     @type bool  $known Whether a usable orderby context was available.
	 *     @type bool  $value Whether 'meta_value' or 'meta_value_num' is referenced.
	 *     @type array $keys  Set of orderby tokens, keyed by token with value true.
	 * }
	 */
	protected function get_orderby_clause_references( $context ) {
		$references = array(
			'known' => false,
			'value' => false,
			'keys'  => array(),
		);

		// A usable context must expose an array of query variables.
		if ( ! is_object( $context ) || ! isset( $context->query_vars ) || ! is_array( $context->query_vars ) ) {
			return $references;
		}

		$references['known'] = true;

		if ( ! isset( $context->query_vars['orderby'] ) ) {
			return $references;
		}

		$orderby = $context->query_vars['orderby'];
		$tokens  = array();

		if ( is_array( $orderby ) ) {
			/*
			 * 'orderby' may be an associative array of field => order (the common
			 * WP_Query form) or a plain list of fields. Collect both the string
			 * keys and the string values so either form is covered.
			 */
			foreach ( $orderby as $key => $value ) {
				if ( is_string( $key ) ) {
					$tokens[] = $key;
				}

				if ( is_string( $value ) ) {
					$tokens[] = $value;
				}
			}
		} elseif ( is_string( $orderby ) ) {
			// A string 'orderby' is a whitespace- and/or comma-separated list.
			$tokens = preg_split( '/[\s,]+/', $orderby, -1, PREG_SPLIT_NO_EMPTY );
			if ( false === $tokens ) {
				$tokens = array();
			}
		}

		foreach ( $tokens as $token ) {
			if ( 'meta_value' === $token || 'meta_value_num' === $token ) {
				$references['value'] = true;
			}

			$references['keys'][ $token ] = true;
		}

		return $references;
	}

	/**
	 * Determines whether a first-order clause can be safely expressed as a
	 * correlated EXISTS subquery in the WHERE clause instead of a table JOIN.
	 *
	 * "Negative" value comparisons ('!=', 'NOT IN', 'NOT LIKE', 'NOT BETWEEN')
	 * only need to test whether a matching meta row exists for a given primary
	 * row; they never expose the meta value to the outer query's SELECT or
	 * ORDER BY. Such a clause therefore produces exactly the same set of primary
	 * rows whether it is expressed as an INNER JOIN or as an
	 * `EXISTS ( SELECT 1 FROM ... )` subquery, so the JOIN can be avoided.
	 *
	 * To guarantee byte-identical result sets, a clause is eligible only when
	 * all of the following hold:
	 *
	 *  - The value comparison operator is one of '!=', 'NOT IN', 'NOT LIKE' or
	 *    'NOT BETWEEN'.
	 *  - The key comparison is a simple equality ('='), so the folded meta_key
	 *    condition is a straightforward match and the clause does not rely on the
	 *    separate negative-key `NOT EXISTS` handling.
	 *  - The clause supplies both a string 'key' and a 'value'.
	 *  - The query contains no 'OR' relation anywhere. When an 'OR' relation is
	 *    present, the implicit "row must exist" requirement of the INNER JOINs
	 *    (which are merged into a single FROM clause across the whole tree) is
	 *    part of the result contract; converting a JOIN to EXISTS would change
	 *    which rows match, so the JOIN is preserved.
	 *  - The clause does not share a key with another "negative" sibling under an
	 *    'AND' relation. Such same-key clauses are deliberately merged onto a
	 *    single shared JOIN (see find_compatible_table_alias()) so that one meta
	 *    row must satisfy every condition; independent EXISTS subqueries would
	 *    relax that semantics.
	 *  - The clause's meta value is not referenced by the outer query's
	 *    'orderby'. WP_Query::parse_orderby() (and the equivalent logic in the
	 *    other primary query classes) emits "{$alias}.meta_value" in the ORDER
	 *    BY when 'orderby' names 'meta_value', 'meta_value_num', the primary
	 *    clause's meta key, or a named clause key; an EXISTS subquery exposes no
	 *    such column to sort on, so the JOIN must be preserved. When the orderby
	 *    context is unknown (for example a null or non-primary caller), the JOIN
	 *    is likewise preserved so the generated SQL stays byte-identical to the
	 *    pre-optimization behavior. The references are collected in
	 *    WP_Meta_Query::get_orderby_clause_references().
	 *
	 * @since 7.0.0
	 *
	 * @param array      $clause            Query clause.
	 * @param array      $parent_query      Parent query of $clause.
	 * @param string     $meta_compare      Normalized meta value comparison operator.
	 * @param string     $meta_compare_key  Normalized meta key comparison operator.
	 * @param bool       $is_primary_clause Whether this clause is the primary (first) meta
	 *                                      clause, whose alias 'meta_value'/'meta_value_num'
	 *                                      orderby resolves to.
	 * @param int|string $clause_key        The clause's key within the meta query. A string
	 *                                      for named clauses (which may be referenced by the
	 *                                      outer 'orderby'), an integer for positional clauses.
	 * @return bool Whether the clause can be expressed as an EXISTS subquery.
	 */
	private function is_meta_value_exists_clause( $clause, $parent_query, $meta_compare, $meta_compare_key, $is_primary_clause, $clause_key ) {
		/*
		 * Only "negative" value comparisons can be expressed as EXISTS without
		 * needing to expose the meta value to the outer SELECT/ORDER BY.
		 */
		$negative_value_operators = array( '!=', 'NOT IN', 'NOT LIKE', 'NOT BETWEEN' );
		if ( ! in_array( $meta_compare, $negative_value_operators, true ) ) {
			return false;
		}

		// The clause must test a specific key/value pair with a simple key match.
		if ( '=' !== $meta_compare_key ) {
			return false;
		}

		if ( ! array_key_exists( 'key', $clause ) || ! is_string( $clause['key'] ) ) {
			return false;
		}

		if ( ! array_key_exists( 'value', $clause ) ) {
			return false;
		}

		/*
		 * A clause whose meta value feeds the outer query's ORDER BY must keep
		 * its table JOIN: parse_orderby() emits "{$alias}.meta_value" in the
		 * ORDER BY, and a correlated EXISTS subquery exposes no such column to
		 * sort on. When the orderby context is unknown (a null or non-primary
		 * caller, as in isolated unit tests), keep the JOIN unconditionally so
		 * the generated SQL stays byte-identical to the pre-optimization
		 * behavior for those callers.
		 */
		if ( ! $this->meta_value_orderby_refs['known'] ) {
			return false;
		}

		/*
		 * 'meta_value' and 'meta_value_num' - and an 'orderby' equal to the
		 * primary clause's own meta key - all resolve to the primary (first)
		 * meta clause's alias, so the primary clause must keep its JOIN whenever
		 * any of them is referenced.
		 */
		if ( $is_primary_clause ) {
			if ( $this->meta_value_orderby_refs['value'] ) {
				return false;
			}

			if ( isset( $this->meta_value_orderby_refs['keys'][ $clause['key'] ] ) ) {
				return false;
			}
		}

		/*
		 * A named clause referenced by its key in the outer 'orderby' resolves
		 * to that clause's alias and must likewise keep its JOIN. Positional
		 * (integer-keyed) clauses cannot be referenced by name, so only string
		 * clause keys are considered here.
		 */
		if ( is_string( $clause_key ) && '' !== $clause_key && isset( $this->meta_value_orderby_refs['keys'][ $clause_key ] ) ) {
			return false;
		}

		/*
		 * When an OR relation exists anywhere in the query, the INNER JOINs
		 * (merged into a single FROM clause for the whole tree) impose an implicit
		 * existence requirement that is part of the result contract. Preserve the
		 * JOIN in that case to keep result sets byte-identical.
		 */
		if ( $this->has_or_relation() ) {
			return false;
		}

		/*
		 * Clauses joined by AND that share a key with another "negative" sibling
		 * are deliberately merged onto a single shared JOIN (see
		 * find_compatible_table_alias()) so that a single meta row must satisfy
		 * every condition. Converting them to independent EXISTS subqueries would
		 * change that semantics, so such clauses keep their JOIN. 'NOT BETWEEN' is
		 * never shared, so it is always eligible.
		 */
		$shareable_operators = array( '!=', 'NOT IN', 'NOT LIKE' );
		if ( in_array( $meta_compare, $shareable_operators, true ) ) {
			$shareable_same_key = 0;

			foreach ( $parent_query as $sibling ) {
				if ( ! is_array( $sibling ) || ! $this->is_first_order_clause( $sibling ) ) {
					continue;
				}

				if ( ! isset( $sibling['key'] ) || ! is_string( $sibling['key'] ) || $sibling['key'] !== $clause['key'] ) {
					continue;
				}

				if ( isset( $sibling['compare'] ) ) {
					$sibling_compare = strtoupper( $sibling['compare'] );
				} else {
					$sibling_compare = isset( $sibling['value'] ) && is_array( $sibling['value'] ) ? 'IN' : '=';
				}

				if ( in_array( $sibling_compare, $shareable_operators, true ) ) {
					++$shareable_same_key;
				}
			}

			// More than one shareable same-key clause means they share a JOIN.
			if ( $shareable_same_key > 1 ) {
				return false;
			}
		}

		return true;
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
