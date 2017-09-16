<?php

namespace TenUp\P2P\Tests\QueryIntegration;

use TenUp\P2P\Plugin;
use TenUp\P2P\QueryIntegration\RelationshipQuery;
use TenUp\P2P\Registry;
use TenUp\P2P\Tests\P2PTestCase;

class RelationshipQueryTest extends P2PTestCase {

	public function setUp() {
		parent::setUp();

		// Force a clear registry for each test
		$plugin = Plugin::instance();
		$plugin->registry = new Registry();
		$plugin->registry->setup();
	}

	public function test_relation_parsing() {
		// With nothing, relation should default to and
		$query = new RelationshipQuery( array() );
		$this->assertEquals( 'AND', $query->relation );

		// Test with valid AND
		$query = new RelationshipQuery( array( 'relation' => 'AND' ) );
		$this->assertEquals( 'AND', $query->relation );

		// Test with valid OR
		$query = new RelationshipQuery( array( 'relation' => 'OR' ) );
		$this->assertEquals( 'OR', $query->relation );

		// Test with weird capitalization
		$query = new RelationshipQuery( array( 'relation' => 'aNd' ) );
		$this->assertEquals( 'AND', $query->relation );
		$query = new RelationshipQuery( array( 'relation' => 'oR' ) );
		$this->assertEquals( 'OR', $query->relation );

		// Test completely invalid defaults to AND
		$query = new RelationshipQuery( array( 'relationship' => 'any' ) );
		$this->assertEquals( 'AND', $query->relation );
		
		// Test empty defaults to AND
		$query = new RelationshipQuery( array( 'relationship' => '' ) );
		$this->assertEquals( 'AND', $query->relation );

		// test incorrect capitalization of the key
		// testing or, since that is not default, we'll know it worked
		$query = new RelationshipQuery( array( 'RELATION' => 'OR' ) );
		$this->assertEquals( 'OR', $query->relation );
	}

	public function test_top_level_segments_are_reformatted_into_nested_arrays_correctly() {
		$query = new RelationshipQuery( array(
			'related_to_post' => '25',
			'type' => 'basic',
		) );
		$expected = array(
			array(
				'related_to_post' => '25',
				'type' => 'basic',
			)
		);
		$this->assertEquals( $expected, $query->segments );


		$query = new RelationshipQuery( array(
			'related_to_user' => '1',
			'type' => 'owner',
		) );
		$expected = array(
			array(
				'related_to_user' => '1',
				'type' => 'owner',
			)
		);
		$this->assertEquals( $expected, $query->segments );


		// Test top level keys AND segments in arrays
		$query = new RelationshipQuery( array(
			'related_to_post' => '25',
			'type' => 'complex',
			array(
				'related_to_post' => '50',
				'type' => 'basic',
			),
		) );
		$expected = array(
			array(
				'related_to_post' => '25',
				'type' => 'complex',
			),
			array(
				'related_to_post' => '50',
				'type' => 'basic',
			),
		);
		$this->assertEquals( $expected, $query->segments );
	}

	public function test_invalid_segments_are_recognized_as_invalid() {
		$query = new RelationshipQuery( array() );

		$this->assertFalse( $query->is_valid_segment( array() ) );
		$this->assertFalse( $query->is_valid_segment( array( 'type' => 'basic' ) ) );
		$this->assertFalse( $query->is_valid_segment( array( 'related_to_post' ) ) );
		$this->assertFalse( $query->is_valid_segment( array( 'related_to_user' ) ) );
	}

	public function test_valid_segments_are_recognized_as_valid() {
		$query = new RelationshipQuery( array() );

		$this->assertTrue( $query->is_valid_segment( array(
			'type' => 'basic',
			'related_to_post' => 45,
		) ) );

		$this->assertTrue( $query->is_valid_segment( array(
			'type' => 'owner',
			'related_to_user' => 1,
		) ) );
	}

	public function test_combined_segments_are_invalid() {
		$query = new RelationshipQuery( array() );

		$this->assertFalse( $query->is_valid_segment( array(
			'type' => 'basic',
			'related_to_post' => 45,
			'related_to_user' => 2,
		) ) );
	}

	public function test_valid_segments_are_tracked() {
		$query = new RelationshipQuery( array() );
		$this->assertFalse( $query->has_valid_segments() );

		$query = new RelationshipQuery( array(
			'type' => 'basic',
			'related_to_post' => 25,
		));
		$this->assertTrue( $query->has_valid_segments() );

		$query = new RelationshipQuery( array(
			array(
				'type' => 'complex',
				'related_to_post' => 25,
			)
		) );
		$this->assertTrue( $query->has_valid_segments() );

		$query = new RelationshipQuery( array(
			array(
				'related_to_user' => 2,
				'type' => 'owner',
			)
		) );
		$this->assertTrue( $query->has_valid_segments() );
	}

	public function test_generate_where_clause() {
		// Should return nothing, since the relationship isn't defined yet
		$query = new RelationshipQuery(array(
			'type' => 'basic',
			'related_to_post' => 1,
		));
		$expected = '';
		$this->assertEquals( $expected, $query->where );

		// Should also return nothing, since also not defined
		$query = new RelationshipQuery(array(
			'type' => 'owner',
			'related_to_user' => 2,
		));
		$expected = '';
		$this->assertEquals( $expected, $query->where );


		$registry = Plugin::instance()->get_registry();
		$registry->define_post_to_post( 'post', 'post', 'basic' );
		$registry->define_post_to_post( 'post', 'post', 'complex' );


		// If we end up with all invalid segments, we should have no changes to where
		$query = new RelationshipQuery( array() );
		$expected = '';
		$this->assertEquals( $expected, $query->where );


		$query = new RelationshipQuery( array(
			'type' => 'basic',
			'related_to_post' => 1
		) );
		$expected = " and ((p2p1.id2 = 1 and p2p1.type = 'basic'))";
		$this->assertEquals( $expected, $query->where );


		$query = new RelationshipQuery( array(
			array(
				'type' => 'basic',
				'related_to_post' => 2
			),
			array(
				'type' => 'basic',
				'related_to_post' => 3,
			),
			'relation' => 'OR',
		) );
		$expected = " and ((p2p1.id2 = 2 and p2p1.type = 'basic') OR (p2p2.id2 = 3 and p2p2.type = 'basic'))";
		$this->assertEquals( $expected, $query->where );


		$query = new RelationshipQuery( array(
			array(
				'type' => 'basic',
				'related_to_post' => 2
			),
			array(
				'type' => 'complex',
				'related_to_post' => 4,
			),
			'relation' => 'AND',
		) );
		$expected = " and ((p2p1.id2 = 2 and p2p1.type = 'basic') AND (p2p2.id2 = 4 and p2p2.type = 'complex'))";
		$this->assertEquals( $expected, $query->where );
	}

	public function test_generate_join_clause() {
		global $wpdb;

		// Should return nothing, since the relationship isn't defined yet
		$query = new RelationshipQuery(array(
			'type' => 'basic',
			'related_to_post' => 1,
		));
		$expected = '';
		$this->assertEquals( $expected, $query->join );


		$registry = Plugin::instance()->get_registry();
		$registry->define_post_to_post( 'post', 'post', 'basic' );
		$registry->define_post_to_post( 'post', 'post', 'complex' );


		$query = new RelationshipQuery( array(
			'type' => 'basic',
			'related_to_post' => 1
		) );
		$expected = " inner join {$wpdb->prefix}post_to_post as p2p1 on {$wpdb->posts}.ID = p2p1.id1";
		$this->assertEquals( $expected, $query->join );


		$query = new RelationshipQuery( array(
			array(
				'type' => 'basic',
				'related_to_post' => 2
			),
			array(
				'type' => 'basic',
				'related_to_post' => 3,
			),
			'relation' => 'OR',
		) );
		$expected = " inner join {$wpdb->prefix}post_to_post as p2p1 on {$wpdb->posts}.ID = p2p1.id1 inner join {$wpdb->prefix}post_to_post as p2p2 on {$wpdb->posts}.ID = p2p2.id1";
		$this->assertEquals( $expected, $query->join );


		$query = new RelationshipQuery( array(
			array(
				'type' => 'basic',
				'related_to_post' => 2
			),
			array(
				'type' => 'complex',
				'related_to_post' => 4,
			),
			'relation' => 'AND',
		) );
		$expected = " inner join {$wpdb->prefix}post_to_post as p2p1 on {$wpdb->posts}.ID = p2p1.id1 inner join {$wpdb->prefix}post_to_post as p2p2 on {$wpdb->posts}.ID = p2p2.id1";
		$this->assertEquals( $expected, $query->join );
	}

}
