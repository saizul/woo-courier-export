<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Unit\Format;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saizul\CourierExport\Format\BuiltInFormats;
use Saizul\CourierExport\Format\FormatRegistry;
use Saizul\CourierExport\Format\TemplateFormat;
use Saizul\CourierExport\Template\Column;
use Saizul\CourierExport\Template\TemplateEngine;

final class FormatsTest extends TestCase {

	public function test_built_in_formats_exist_and_compile(): void {
		$formats = BuiltInFormats::create();
		$this->assertSame( array( 'steadfast', 'pathao', 'redx', 'generic' ), array_keys( $formats ) );
		$engine = new TemplateEngine();
		foreach ( $formats as $format ) {
			foreach ( $format->columns() as $column ) {
				$engine->compile( $column->template );
			}
		}
		$this->addToAssertionCount( 1 );
	}

	public function test_steadfast_headers(): void {
		$headers = BuiltInFormats::create()['steadfast']->headers();
		$this->assertSame(
			array( 'invoice', 'recipient_name', 'recipient_phone', 'alternative_phone', 'recipient_address', 'cod_amount', 'note', 'item_description' ),
			$headers
		);
	}

	public function test_pathao_and_redx_key_columns(): void {
		$formats = BuiltInFormats::create();
		$this->assertContains( 'amount_to_collect', $formats['pathao']->headers() );
		$this->assertContains( 'merchant_order_id', $formats['pathao']->headers() );
		$this->assertContains( 'cash_collection_amount', $formats['redx']->headers() );
		$this->assertContains( 'parcel_weight', $formats['redx']->headers() );
	}

	public function test_overrides_and_labels(): void {
		$formats = BuiltInFormats::create( array( 'redx' => "Name = {recipient.name}\nCOD = {cod_amount}", 'pathao' => '  ' ), array( 'redx' => 'RedX (custom)' ) );
		$this->assertSame( array( 'Name', 'COD' ), $formats['redx']->headers() );
		$this->assertSame( 'RedX (custom)', $formats['redx']->label() );
		$this->assertContains( 'amount_to_collect', $formats['pathao']->headers() );
	}

	public function test_template_format_validation(): void {
		$this->expectException( InvalidArgumentException::class );
		new TemplateFormat( 'Bad Id', 'x', array( new Column( 'a', 'b' ) ) );
	}

	public function test_template_format_needs_columns(): void {
		$this->expectException( InvalidArgumentException::class );
		new TemplateFormat( 'x', 'x', array() );
	}

	public function test_template_format_rejects_non_columns(): void {
		$this->expectException( InvalidArgumentException::class );
		new TemplateFormat( 'x', 'x', array( 'nope' ) );
	}

	public function test_with_columns(): void {
		$format = TemplateFormat::from_definition( 'ecourier', 'eCourier', 'A = {order.number}' );
		$copy   = $format->with_columns( array( new Column( 'B', '{order.id}' ) ) );
		$this->assertSame( array( 'A' ), $format->headers() );
		$this->assertSame( array( 'B' ), $copy->headers() );
		$this->assertSame( 'ecourier', $copy->id() );
	}

	public function test_registry(): void {
		$registry = new FormatRegistry( BuiltInFormats::create() );
		$this->assertTrue( $registry->has( 'redx' ) );
		$registry->add( TemplateFormat::from_definition( 'paperfly', 'Paperfly', 'Ref = {order.number}' ) );
		$this->assertSame( 'Paperfly', $registry->get( 'paperfly' )->label() );
		$registry->remove( 'redx' );
		$this->assertFalse( $registry->has( 'redx' ) );
		$this->assertSame( array( 'steadfast', 'pathao', 'generic', 'paperfly' ), array_keys( $registry->labels() ) );
		$this->assertCount( 4, $registry->all() );
	}

	public function test_registry_unknown_format(): void {
		$this->expectException( InvalidArgumentException::class );
		( new FormatRegistry() )->get( 'nope' );
	}
}
