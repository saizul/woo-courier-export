<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;
use Saizul\CourierExport\Template\TemplateEngine;
use Saizul\CourierExport\Template\TemplateException;

final class TemplateEngineTest extends TestCase {

	/** @var TemplateEngine */
	private $engine;

	/** @var array<string,mixed> */
	private $context;

	protected function setUp(): void {
		$this->engine  = new TemplateEngine();
		$this->context = array(
			'order'     => array(
				'number' => '1001',
				'date'   => '2026-11-01 14:30:00',
				'total'  => 1560.5,
				'note'   => "Call first\nthen ring",
				'items'  => array(
					array( 'name' => 'Panjabi', 'sku' => 'PJ' ),
					array( 'name' => 'Attar', 'sku' => 'AT' ),
				),
				'tags'   => array( 'gift', 'fragile' ),
				'empty'  => '',
				'zero'   => 0,
				'flag'   => true,
			),
			'recipient' => array(
				'name'  => '  rahim   uddin ',
				'phone' => '+8801711234567',
			),
			'weight_kg' => 1.25,
		);
	}

	private function render( string $template ): string {
		return $this->engine->render( $template, $this->context );
	}

	public function test_literal_text_and_paths(): void {
		$this->assertSame( 'Order #1001', $this->render( 'Order #{order.number}' ) );
		$this->assertSame( 'plain text', $this->render( 'plain text' ) );
		$this->assertSame( '', $this->render( '' ) );
	}

	public function test_missing_values_render_empty(): void {
		$this->assertSame( '[]', $this->render( '[{order.missing}]' ) );
		$this->assertSame( '', $this->render( '{nothing.here.at.all}' ) );
	}

	public function test_list_index_access(): void {
		$this->assertSame( 'Attar', $this->render( '{order.items.1.name}' ) );
	}

	public function test_scalars_are_stringified(): void {
		$this->assertSame( '1560.5', $this->render( '{order.total}' ) );
		$this->assertSame( '0', $this->render( '{order.zero}' ) );
		$this->assertSame( '1', $this->render( '{order.flag}' ) );
		$this->assertSame( 'gift, fragile', $this->render( '{order.tags}' ) );
		$this->assertSame( 'Panjabi, Attar', $this->render( '{order.items}' ) );
	}

	public function test_escaped_braces(): void {
		$this->assertSame( '{1001}', $this->render( '{{{order.number}}}' ) );
		$this->assertSame( 'a {b} c', $this->render( 'a {{b}} c' ) );
	}

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public function filterProvider(): array {
		return array(
			'upper'           => array( '{recipient.name|trim|upper}', 'RAHIM UDDIN' ),
			'lower'           => array( '{order.number|lower}', '1001' ),
			'title'           => array( '{recipient.name|trim|title}', 'Rahim Uddin' ),
			'trim collapses'  => array( '[{recipient.name|trim}]', '[rahim uddin]' ),
			'truncate'        => array( '{recipient.name|trim|truncate:5}', 'rahim' ),
			'truncate dots'   => array( '{recipient.name|trim|truncate:8:...}', 'rahim...' ),
			'truncate short'  => array( '{order.number|truncate:10}', '1001' ),
			'number'          => array( '{order.total|number:2}', '1560.50' ),
			'number zero dp'  => array( '{weight_kg|number:0}', '1' ),
			'number empty'    => array( '[{order.empty|number}]', '[]' ),
			'int'             => array( '{order.total|int}', '1561' ),
			'default empty'   => array( '{order.empty|default:N/A}', 'N/A' ),
			'default missing' => array( '{order.missing|default:"x: y"}', 'x: y' ),
			'default keeps'   => array( '{order.number|default:N/A}', '1001' ),
			'default zero'    => array( '{order.zero|default:N/A}', '0' ),
			'replace'         => array( '{order.number|replace:0:o}', '1oo1' ),
			'digits'          => array( '{recipient.phone|digits}', '8801711234567' ),
			'phone'           => array( '{recipient.phone|phone}', '01711234567' ),
			'phone intl'      => array( '{recipient.phone|phone_intl}', '+8801711234567' ),
			'date'            => array( '{order.date|date:d/m/Y}', '01/11/2026' ),
			'date default'    => array( '{order.date|date}', '2026-11-01' ),
			'join'            => array( '{order.items|join:" | "}', 'Panjabi | Attar' ),
			'join key'        => array( '{order.items|join:;:sku}', 'PJ;AT' ),
			'first'           => array( '{order.tags|first}', 'gift' ),
			'grams'           => array( '{weight_kg|grams}', '1250' ),
			'prefix'          => array( '{order.number|prefix:INV-}', 'INV-1001' ),
			'prefix empty'    => array( '[{order.empty|prefix:INV-}]', '[]' ),
			'suffix'          => array( '{weight_kg|suffix:" kg"}', '1.25 kg' ),
			'single'          => array( '{order.note|single}', 'Call first then ring' ),
			'chain'           => array( '{order.note|single|upper|truncate:10}', 'CALL FIRST' ),
			'spaces in expr'  => array( '{ order.number | prefix:#}', '#1001' ),
		);
	}

	/**
	 * @dataProvider filterProvider
	 */
	public function test_filters( string $template, string $expected ): void {
		$this->assertSame( $expected, $this->render( $template ) );
	}

	public function test_invalid_phone_passes_through(): void {
		$this->assertSame( 'call me', $this->engine->render( '{p|phone}', array( 'p' => 'call me' ) ) );
	}

	public function test_bad_date_passes_through(): void {
		$this->assertSame( 'soon', $this->engine->render( '{d|date}', array( 'd' => 'soon' ) ) );
	}

	public function test_custom_filter(): void {
		$this->engine->add_filter(
			'bdt',
			static function ( $value, $symbol = 'Tk' ) {
				return $symbol . ' ' . number_format( (float) $value );
			}
		);
		$this->assertSame( 'Tk 1,561', $this->render( '{order.total|bdt}' ) );
		$this->assertSame( '৳ 1,561', $this->render( '{order.total|bdt:৳}' ) );
		$this->assertContains( 'bdt', $this->engine->filter_names() );
	}

	public function test_invalid_custom_filter_name(): void {
		$this->expectException( TemplateException::class );
		$this->engine->add_filter( 'Bad-Name', 'strval' );
	}

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public function errorProvider(): array {
		return array(
			'unknown filter' => array( '{order.number|shout}', 'Unknown filter "shout"' ),
			'unclosed'       => array( 'Order {order.number', 'Unclosed "{"' ),
			'stray close'    => array( 'Order }', 'Unexpected "}"' ),
			'empty expr'     => array( '{}', 'Invalid value name ""' ),
			'bad path'       => array( '{order..number}', 'Invalid value name' ),
			'php code'       => array( '{system("ls")}', 'Invalid value name' ),
			'nested'         => array( '{order.{x}}', 'Unclosed' ),
		);
	}

	/**
	 * @dataProvider errorProvider
	 */
	public function test_compile_errors( string $template, string $message ): void {
		try {
			$this->engine->compile( $template );
			$this->fail( 'Expected TemplateException' );
		} catch ( TemplateException $e ) {
			$this->assertStringContainsString( $message, $e->getMessage() );
		}
	}

	public function test_validate_reports_errors_and_unknown_paths(): void {
		$this->assertSame( array(), $this->engine->validate( '{order.number}' ) );
		$this->assertCount( 1, $this->engine->validate( '{order.number|nope}' ) );
		$errors = $this->engine->validate( '{order.number} {order.typo}', $this->context );
		$this->assertSame( array( 'Unknown value "order.typo".' ), $errors );
	}

	public function test_paths(): void {
		$this->assertSame(
			array( 'order.number', 'recipient.name' ),
			$this->engine->paths( '{order.number}-{recipient.name|upper}-{order.number}' )
		);
	}

	public function test_compile_is_cached(): void {
		$first = $this->engine->compile( 'A {order.number|upper}' );
		$this->assertSame( $first, $this->engine->compile( 'A {order.number|upper}' ) );
		$this->assertSame( array( 'A ' ), array_slice( $first, 0, 1 ) );
	}

	public function test_stringify_float_and_objects(): void {
		$this->assertSame( '0.1', TemplateEngine::stringify( 0.1 ) );
		$this->assertSame( '1500', TemplateEngine::stringify( 1500.0 ) );
		$this->assertSame( '0', TemplateEngine::stringify( -0.0 ) );
		$this->assertSame( '', TemplateEngine::stringify( new \stdClass() ) );
	}
}
