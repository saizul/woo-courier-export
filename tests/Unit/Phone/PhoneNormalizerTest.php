<?php
declare(strict_types=1);

namespace Saizul\CourierExport\Tests\Unit\Phone;

use PHPUnit\Framework\TestCase;
use Saizul\CourierExport\Phone\PhoneNormalizer;
use Saizul\CourierExport\Phone\PhoneResult;

final class PhoneNormalizerTest extends TestCase {

	/** @var PhoneNormalizer */
	private $phones;

	protected function setUp(): void {
		$this->phones = new PhoneNormalizer();
	}

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public function validProvider(): array {
		return array(
			'local'                  => array( '01711234567', '01711234567' ),
			'plus 880'               => array( '+8801711234567', '01711234567' ),
			'880 no plus'            => array( '8801811234567', '01811234567' ),
			'00880'                  => array( '008801911234567', '01911234567' ),
			'spaces and dashes'      => array( '017-1123 4567', '01711234567' ),
			'dots'                   => array( '017.112.34567', '01711234567' ),
			'parentheses'            => array( '(+880) 1711-234567', '01711234567' ),
			'missing leading zero'   => array( '1711234567', '01711234567' ),
			'trunk zero after code'  => array( '+880 (0)1711234567', '01711234567' ),
			'bangla digits'          => array( '০১৭১১২৩৪৫৬৭', '01711234567' ),
			'bangla with +৮৮০'       => array( '+৮৮০১৫১১২৩৪৫৬৭', '01511234567' ),
			'two numbers in a field' => array( '01711234567, 01811234567', '01711234567' ),
			'slash separated'        => array( '01711234567/01911234567', '01711234567' ),
			'label prefix'           => array( 'Mobile: 01611234567', '01611234567' ),
			'operator 013'           => array( '01311234567', '01311234567' ),
			'operator 014'           => array( '01411234567', '01411234567' ),
			'surrounding whitespace' => array( "  01711234567\n", '01711234567' ),
		);
	}

	/**
	 * @dataProvider validProvider
	 */
	public function test_valid_numbers_are_normalised( string $input, string $expected ): void {
		$result = $this->phones->normalize( $input );
		$this->assertTrue( $result->is_valid(), (string) $result->reason() );
		$this->assertSame( $expected, $result->local() );
		$this->assertSame( '+88' . $expected, $result->international() );
		$this->assertSame( $input, $result->raw() );
	}

	/**
	 * @return array<string,array{0:string|null,1:string}>
	 */
	public function invalidProvider(): array {
		return array(
			'null'             => array( null, PhoneResult::REASON_EMPTY ),
			'empty'            => array( '', PhoneResult::REASON_EMPTY ),
			'spaces'           => array( '   ', PhoneResult::REASON_EMPTY ),
			'too short'        => array( '0171123456', PhoneResult::REASON_TOO_SHORT ),
			'too long'         => array( '017112345678', PhoneResult::REASON_TOO_LONG ),
			'landline'         => array( '0255012345', PhoneResult::REASON_TOO_SHORT ),
			'dhaka landline'   => array( '02-55012345x', PhoneResult::REASON_NOT_A_NUMBER ),
			'not mobile'       => array( '02955012345', PhoneResult::REASON_NOT_MOBILE ),
			'operator 012'     => array( '01211234567', PhoneResult::REASON_UNKNOWN_OPERATOR ),
			'operator 011'     => array( '01111234567', PhoneResult::REASON_UNKNOWN_OPERATOR ),
			'letters'          => array( 'call me', PhoneResult::REASON_NOT_A_NUMBER ),
			'placeholder'      => array( '01711111111', PhoneResult::REASON_PLACEHOLDER ),
			'placeholder zero' => array( '01900000000', PhoneResult::REASON_PLACEHOLDER ),
			'foreign number'   => array( '+919876543210', PhoneResult::REASON_TOO_LONG ),
		);
	}

	/**
	 * @dataProvider invalidProvider
	 */
	public function test_invalid_numbers_report_a_reason( ?string $input, string $reason ): void {
		$result = $this->phones->normalize( $input );
		$this->assertFalse( $result->is_valid() );
		$this->assertNull( $result->local() );
		$this->assertNull( $result->international() );
		$this->assertSame( $reason, $result->reason() );
	}

	public function test_shortcuts(): void {
		$this->assertSame( '01711234567', $this->phones->to_local( '+8801711234567' ) );
		$this->assertSame( '+8801711234567', $this->phones->to_international( '01711234567' ) );
		$this->assertNull( $this->phones->to_local( 'nope' ) );
		$this->assertNull( $this->phones->to_international( '' ) );
	}

	public function test_operator_prefix(): void {
		$this->assertSame( '019', $this->phones->normalize( '01911234567' )->operator_prefix() );
		$this->assertNull( $this->phones->normalize( 'x' )->operator_prefix() );
	}
}
