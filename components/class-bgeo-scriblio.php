<?php
/*
 * This class presents bGeo data as facets in Scriblio.
 * See https://github.com/misterbisson/scriblio
 */

class bGeo_Scriblio implements Facet
{

	public $version = 1;
	public $ttl = 18013; // a little longer than 5 hours

	/**
	 * Function description
	 */
	public function __construct( $name, $args, $facets_object )
	{
		$this->name = $name;
		$this->args = $args;
		$this->facets = $facets_object;

		// @TODO: remove all mention of these, this class is specific to geos
		$this->taxonomy = $args['taxonomy'] ? $args['taxonomy'] : $this->name;
		$this->facets->_tax_to_facet[ $this->taxonomy ] = $this->name;
		$this->facets->_facet_to_tax[ $this->name ] = $this->taxonomy;
		$taxonomy = get_taxonomy( $this->taxonomy );
		$this->label = $taxonomy->label;
		$this->labels = $taxonomy->labels;
		if ( $taxonomy->query_var )
			$this->query_var = $taxonomy->query_var;

	}//end __construct

	/**
	 * Function description
	 */
	public function register_query_var()
	{

		// @TODO: the query var is known from the registered taxonomy qv
		if ( TRUE === $this->query_var )
		{
			$this->query_var = $this->name;
		}

		return $this->query_var;
	}//end register_query_var

	/**
	 * Function description
	 */
	public function parse_query( $query_terms, $unused_wp_query )
	{

		// identify the terms in this query
		foreach ( array_filter( array_map( 'trim', (array) preg_split( '/[,\+\|\/]/', $query_terms ) ) ) as $val )
		{
			if ( $term = get_term_by( 'slug', $val, $this->taxonomy ) )
			{
				$this->selected_terms[ $term->slug ] = $term;
			}
		}

		return $this->selected_terms;
	}//end parse_query

	/**
	 * Function description
	 */
	public function get_terms_in_corpus()
	{
		if ( isset( $this->terms_in_corpus ) )
		{
			return $this->terms_in_corpus;
		}

		scriblio()->timer( 'bgeo_scriblio::get_terms_in_corpus' );
		$timer_notes = 'from cache';

		if ( ! $this->terms_in_corpus = wp_cache_get( 'terms-in-corpus-'. $this->taxonomy, 'scrib-facet-taxonomy' ) )
		{
			$timer_notes = 'from query';

			$terms = get_terms( $this->taxonomy, array( 'number' => 1000, 'orderby' => 'count', 'order' => 'DESC' ) );
			$terms = apply_filters( 'scriblio_facet_taxonomy_terms', $terms );

			$this->terms_in_corpus = array();
			foreach ( $terms as $term )
			{
				$this->terms_in_corpus[] = (object) array(
					'facet' => $this->facets->_tax_to_facet[ $term->taxonomy ],
					'slug' => $term->slug,
					'name' => $term->name,
					'description' => $term->description,
					'term_id' => $term->term_id,
					'term_taxonomy_id' => $term->term_taxonomy_id,
					'count' => $term->count,
				);
			}//end foreach

			wp_cache_set( 'terms-in-corpus-'. $this->taxonomy, $this->terms_in_corpus, 'scrib-facet-taxonomy', $this->ttl );
		}//end if

		scriblio()->timer( 'bgeo_scriblio::get_terms_in_corpus', $timer_notes );

		return $this->terms_in_corpus;
	}//end get_terms_in_corpus

	/**
	 * Function description
	 */
	public function get_terms_in_found_set()
	{
		if ( isset( $this->facets->_matching_tax_facets[ $this->name ] ) && is_array( $this->facets->_matching_tax_facets[ $this->name ] ) )
		{
			return $this->facets->_matching_tax_facets[ $this->name ];
		}

		$matching_post_ids = $this->facets->get_matching_post_ids();

		// if there aren't any matching post ids, we don't need to query
		if ( ! is_array( $matching_post_ids ) || ! count( $matching_post_ids ) )
		{
			return array();
		}//end if

		scriblio()->timer( 'bgeo_scriblio::get_terms_in_found_set' );
		$timer_notes = 'from cache';

		$cache_key = md5( serialize( $matching_post_ids ) ) . $this->version;
		if ( ! $this->facets->_matching_tax_facets = wp_cache_get( $cache_key . ( scriblio()->cachebuster ? 'CACHEBUSTER' : '' ), 'scrib-facet-taxonomy' ) )
		{
			$timer_notes = 'from query';

			global $wpdb;

			$facets_query = "SELECT b.term_id, c.term_taxonomy_id, b.slug, b.name, a.taxonomy, a.description, COUNT(c.term_taxonomy_id) AS `count`
				FROM $wpdb->term_relationships c
				INNER JOIN $wpdb->term_taxonomy a ON a.term_taxonomy_id = c.term_taxonomy_id
				INNER JOIN $wpdb->terms b ON a.term_id = b.term_id
				WHERE c.object_id IN (". implode( ',', $matching_post_ids ) .")
				GROUP BY c.term_taxonomy_id ORDER BY count DESC LIMIT 2000
				/* generated in bgeo_scriblio::get_terms_in_found_set() */";

			$terms = $wpdb->get_results( $facets_query );

			scriblio()->timer( 'bgeo_scriblio::get_terms_in_found_set::scriblio_facet_taxonomy_terms' );
			$terms = apply_filters( 'scriblio_facet_taxonomy_terms', $terms );
			scriblio()->timer( 'bgeo_scriblio::get_terms_in_found_set::scriblio_facet_taxonomy_terms', count( $terms ) . ' terms' );

			$this->facets->_matching_tax_facets = array();
			foreach ( $terms as $term )
			{
				$this->facets->_matching_tax_facets[ $this->facets->_tax_to_facet[ $term->taxonomy ] ][] = (object) array(
					'facet' => $this->facets->_tax_to_facet[ $term->taxonomy ],
					'slug' => $term->slug,
					'name' => $term->name,
					'count' => $term->count,
					'description' => $term->description,
					'term_id' => $term->term_id,
					'term_taxonomy_id' => $term->term_taxonomy_id,
				);
			}//end foreach

			wp_cache_set( $cache_key, $this->facets->_matching_tax_facets, 'scrib-facet-taxonomy', $this->ttl );
		}//end if

		scriblio()->timer( 'bgeo_scriblio::get_terms_in_found_set', $timer_notes );

		if ( ! isset( $this->facets->_matching_tax_facets[ $this->name ] ) || ! is_array( $this->facets->_matching_tax_facets[ $this->name ] ) )
		{
			return array();
		}
		else
		{
			return $this->facets->_matching_tax_facets[ $this->name ];
		}
	}//end get_terms_in_found_set

	/**
	 * Function description
	 */
	public function get_terms_in_post( $post_id = FALSE )
	{
		if ( ! $post_id )
			$post_id = get_the_ID();

		if ( ! $post_id )
			return FALSE;

		scriblio()->timer( 'bgeo_scriblio::get_terms_in_post' );

		$terms = wp_get_object_terms( $post_id, $this->taxonomy );
		$terms = apply_filters( 'scriblio_facet_taxonomy_terms', $terms );

		$terms_in_post = array();
		foreach ( $terms as $term )
		{
			$terms_in_post[] = (object) array(
				'facet' => $this->facets->_tax_to_facet[ $term->taxonomy ],
				'slug' => $term->slug,
				'name' => $term->name,
				'description' => $term->description,
				'term_id' => $term->term_id,
				'term_taxonomy_id' => $term->term_taxonomy_id,
				'count' => $term->count,
			);
		}//end foreach

		scriblio()->timer( 'bgeo_scriblio::get_terms_in_post' );

		return $terms_in_post;
	}//end get_terms_in_post

	/**
	 * Function description
	 */
	public function selected( $term )
	{
		return( isset( $this->selected_terms[ ( is_object( $term ) ? $term->slug : $term ) ] ) );
	}//end selected

	/**
	 * Function description
	 */
	public function queryterm_add( $term, $current )
	{
		$current[ $term->slug ] = $term;
		return $current;
	}//end queryterm_add

	/**
	 * Function description
	 */
	public function queryterm_remove( $term, $current )
	{
		unset( $current[ $term->slug ] );
		return $current;
	}//end queryterm_remove

	/**
	 * Function description
	 */
	public function permalink( $terms )
	{
		if ( 1 === count( $terms ) )
		{
			$termlink = get_term_link( (int) current( $terms )->term_id, $this->taxonomy );
		}
		else
		{
			// much of this section comes from get_term_link() in /wp-includes/taxonomy.php,
			// but that code can't handle multiple terms in a single taxonomy

			global $wp_rewrite;
			$termlink = $wp_rewrite->get_extra_permastruct( $this->taxonomy );

			if ( empty( $termlink ) ) // dang, we're not using pretty permalinks
			{
				$t = get_taxonomy( $this->taxonomy );
				$termlink = "?$t->query_var=" . implode( '+', array_keys( $terms ) );
			}
			else
			{
				$termlink = str_replace( "%$this->taxonomy%", implode( '+', array_keys( $terms ) ), $termlink );
			}

			$termlink = home_url( user_trailingslashit( $termlink, 'category' ) );
		}// end else

		$termlink = apply_filters( 'scriblio_facet_taxonomy_permalink', $termlink, $terms, $this->taxonomy );
		return $termlink;
	}//end permalink
}//end class
