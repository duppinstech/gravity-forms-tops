<?php
/**
 * Mapping Gravity Forms entry values to TowX fields.
 *
 * @package GF_Tops
 */

use PHPUnit\Framework\TestCase;

class EntryTest extends TestCase {

	private function form() {
		return array(
			'id'     => 1,
			'fields' => array(
				new GF_Tops_Test_Field( 1, 'address' ),
				new GF_Tops_Test_Field( 2, 'name' ),
				new GF_Tops_Test_Field( 3, 'text' ),
				new GF_Tops_Test_Field( 4, 'text' ),
				new GF_Tops_Test_Field(
					5,
					'select',
					array(
						array( 'text' => 'TestMake', 'value' => '10' ),
					)
				),
				new GF_Tops_Test_Field(
					6,
					'select',
					array(
						array( 'text' => 'TestModel', 'value' => '20' ),
					)
				),
				new GF_Tops_Test_Field( 7, 'select', array() ),
			),
		);
	}

	public function test_full_address_is_joined() {
		$entry = array(
			'1.1' => '1 Test St',
			'1.2' => 'Unit 2',
			'1.3' => 'Testville',
			'1.4' => 'TS',
			'1.5' => '00000',
			'1.6' => 'United States',
		);
		$this->assertSame( '1 Test St, Unit 2, Testville, TS 00000, United States', GF_Tops_Entry::get_value( $this->form(), $entry, '1' ) );
	}

	public function test_partial_and_empty_addresses_have_no_stray_separators() {
		$this->assertSame( '1 Test St, Testville, TS', GF_Tops_Entry::get_value( $this->form(), array( '1.1' => '1 Test St', '1.3' => 'Testville', '1.4' => 'TS' ), '1' ) );
		$this->assertSame( '00000', GF_Tops_Entry::get_value( $this->form(), array( '1.5' => '00000' ), '1' ) );
		$this->assertSame( '', GF_Tops_Entry::get_value( $this->form(), array(), '1' ) );
	}

	public function test_address_sub_input_returns_raw_value() {
		$this->assertSame( 'Testville', GF_Tops_Entry::get_value( $this->form(), array( '1.3' => 'Testville' ), '1.3' ) );
	}

	public function test_name_field_combines_parts() {
		$entry = array(
			'2.2' => 'Dr.',
			'2.3' => 'Test',
			'2.6' => 'Caller',
		);
		$this->assertSame( 'Dr. Test Caller', GF_Tops_Entry::get_value( $this->form(), $entry, '2' ) );
	}

	public function test_unmapped_field_is_empty() {
		$this->assertSame( '', GF_Tops_Entry::get_value( $this->form(), array( '3' => 'x' ), '' ) );
		$this->assertSame( '', GF_Tops_Entry::get_value( $this->form(), array( '3' => 'x' ), null ) );
	}

	public function test_vehicle_info_uses_choice_labels_and_falls_back_to_raw_values() {
		$map   = array(
			'year'      => '3',
			'make_key'  => '5',
			'model_key' => '6',
			'color_key' => '7',
		);
		$entry = array(
			'3' => '2020',
			'5' => '10',
			'6' => '20',
			'7' => '30',
		);

		$this->assertSame( '2020 TestMake TestModel 30', GF_Tops_Entry::build_vehicle_info_from_mmc( $this->form(), $entry, $map ) );
	}

	public function test_custom_prefix_lists_only_filled_optional_fields() {
		$map    = array(
			'bill_to_account' => '3',
			'customer_email'  => '4',
			'owner_name'      => '',
		);
		$labels = array(
			'bill_to_account' => 'Bill To Account',
			'customer_email'  => 'Customer Email',
			'owner_name'      => 'Owner Name',
		);

		$this->assertSame(
			"Bill To Account: ACCT-1\n",
			GF_Tops_Entry::build_custom_prefix( $this->form(), array( '3' => 'ACCT-1', '4' => '' ), $map, $labels )
		);
		$this->assertSame( '', GF_Tops_Entry::build_custom_prefix( $this->form(), array(), $map, $labels ) );
	}
}
