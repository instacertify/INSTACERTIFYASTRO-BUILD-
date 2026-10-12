<?php

namespace Tests\Unit;

use App\Support\Standards\IsNumber;
use App\Support\Standards\LibraryContent;
use App\Support\Standards\LibraryData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StandardsLibraryDataTest extends TestCase
{
    public static function isNumbers(): array
    {
        return [
            ['IS 302 (Part 2/Sec 9): 2009', '302-p2-s9', 'IS 302 (Part 2/Sec 9)'],
            ['302-2-9', '302-p2-s9', 'IS 302 (Part 2/Sec 9)'],
            ['IS 302 : PART 2', '302-p2', 'IS 302 (Part 2)'],
            ['459:1992', '459', 'IS 459'],
            ['IS/IEC 60335-2-9', '60335-p2-s9', 'IS/IEC 60335 (Part 2/Sec 9)'],
        ];
    }

    #[DataProvider('isNumbers')]
    public function test_is_numbers_written_differently_share_a_key(string $raw, string $key, string $formatted): void
    {
        $this->assertSame($key, IsNumber::key($raw));
        $this->assertSame($formatted, IsNumber::format($raw));
    }

    public function test_is_number_without_digits_has_no_key(): void
    {
        $this->assertNull(IsNumber::key(null));
        $this->assertNull(IsNumber::key('Verify'));
    }

    public function test_sheet_status_labels_map_to_regulation_statuses(): void
    {
        $this->assertSame('mandatory', LibraryData::status('Mandatory (CRS Registration)')[0]);
        $this->assertSame('upcoming', LibraryData::status('Upcoming mandatory')[0]);
        $this->assertSame('deferred', LibraryData::status('QCO notified - date awaited')[0]);
        $this->assertSame('cdsco', LibraryData::status('Regulated by CDSCO')[0]);
        $this->assertSame('rescinded', LibraryData::status('Mandatory (rescinded)')[0]);
        $this->assertSame('voluntary', LibraryData::status('Voluntary (ISI on request)')[0]);
    }

    public function test_since_reads_dates_and_years_from_order_wording(): void
    {
        $this->assertSame([null, '2010'], LibraryData::since('Mandatory since 2010'));
        $this->assertSame(['2024-10-01', '1 Oct 2024'], LibraryData::since('QCO dated 12-03-2024, eff. 01-10-2024'));
        $this->assertSame(['2026-07-26', '26 Jul 2026'], LibraryData::since('26-07-2026'));
        $this->assertSame(['2024-10-01', 'Phased from Oct 2024'], LibraryData::since('Phased from Oct 2024'));
        $this->assertSame([null, '2014'], LibraryData::since('CRO Phase-II; effective 2014'));
        $this->assertSame([null, null], LibraryData::since('Not under any QCO'));
    }

    public function test_order_drops_compiler_notes_and_voluntary_rows(): void
    {
        $this->assertNull(LibraryData::order('Not under any QCO (verify latest BIS mandatory list)', 'voluntary'));
        $this->assertSame(
            'Electrical Appliances (Quality Control) Order, 2025',
            LibraryData::order('Electrical Appliances (Quality Control) Order, 2025 (verify latest notification); in force', 'mandatory'),
        );
    }

    public function test_units_and_marking_fee_slabs_read_naturally(): void
    {
        $this->assertSame('100 pieces', LibraryData::unit('100 PCS'));
        $this->assertSame('tonne', LibraryData::unit('1 M.T.'));
        $this->assertSame('kilolitre', LibraryData::unit('ONE KILO LITRE'));
        $this->assertNull(LibraryData::unit('Verify unit'));

        $this->assertSame('₹1.75 per tonne', LibraryData::unitFee('1 M.T.', [[1.75, 'For All']]));
        $this->assertSame(
            '₹17.30 per piece for the first 1,500; ₹10.40 per piece thereafter',
            LibraryData::unitFee('1 PCS', [[17.3, 1500], [10.4, 'thereafter']]),
        );
        $this->assertNull(LibraryData::unitFee('1 PCS', []));
    }

    public function test_testing_ignores_placeholder_prices(): void
    {
        $result = LibraryData::testing([
            ['lab' => 'Alpha', 'price' => 40000],
            ['lab' => 'Beta', 'price' => 15000],
            ['lab' => 'Placeholder', 'price' => 200],
        ]);

        $this->assertSame(['test_min' => 15000, 'test_max' => 40000, 'labs' => 2, 'lab_names' => 'Beta; Alpha'], $result);
        $this->assertNull(LibraryData::testing([['lab' => 'Placeholder', 'price' => 100]]));
    }

    public function test_product_names_are_cleaned_for_titles(): void
    {
        $this->assertSame('Hot Rolled Carbon Steel Sheet and Strip', LibraryData::productName('HOT ROLLED CARBON STEEL SHEET AND STRIP - Specification (Second Revision)'));
        $this->assertSame('The National Flag of India (Cotton Khadi)', LibraryData::productName('Specification for The National Flag of India (Cotton Khadi)'));
        $this->assertSame('LED Lamps for General Lighting', LibraryData::productName('LED LAMPS FOR GENERAL LIGHTING'));
    }

    public function test_title_names_stop_at_a_clean_break(): void
    {
        $this->assertSame('Gas Cylinder Valves', LibraryContent::titleName('Gas Cylinder Valves for Small Refrigerant Cylinders', 30));
        $this->assertSame('Carburizing Steels', LibraryContent::titleName('Carburizing Steels for Use in Automobiles', 32));
        $this->assertSame('Fusion Bonded Epoxy Coated Reinforcing Bars', LibraryContent::titleName('Fusion Bonded Epoxy Coated Reinforcing Bars', 36));
        $this->assertSame('Room Air Conditioners', LibraryContent::titleName('Room Air Conditioners', 21));
        $this->assertSame('Particle Filters', LibraryContent::titleName('Particle Filters Used in Respiratory Protective Devices', 26));
    }

    public function test_rupees_use_indian_grouping(): void
    {
        $this->assertSame('1,50,000', LibraryData::rupees(150000));
        $this->assertSame('17.30', LibraryData::rupees(17.3, 2));
        $this->assertSame('16', LibraryData::rupees(16, 2));
        $this->assertSame([150000, 250000, 'per model'], LibraryData::priceRange('1,50,000 - 2,50,000 / model'));
    }
}
