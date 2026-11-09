<?php

namespace App\Support;

use Carbon\CarbonInterface;
use RuntimeException;
use ZipArchive;

/**
 * Egyszerű .xlsx író egy munkalapra: félkövér, rögzített fejléc, szűrő, oszlopszélességek, dátumok Excel-dátumként.
 * Külső csomag nélkül, a PHP zip bővítményével (az .xlsx egy zip-be csomagolt XML-gyűjtemény).
 */
class XlsxWriter
{
    /** @var list<array{title: string, width: int}> */
    private array $columns = [];

    /** @var list<list<string|int|float|CarbonInterface|null>> */
    private array $rows = [];

    public function __construct(private string $sheetName = 'Munkalap1') {}

    /** @param list<array{title: string, width: int}> $columns */
    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /** @param list<string|int|float|CarbonInterface|null> $row */
    public function addRow(array $row): static
    {
        $this->rows[] = $row;

        return $this;
    }

    /** A kész fájl tartalma (bináris). */
    public function toString(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Az Excel fájl nem hozható létre.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->escape(mb_substr($this->sheetName, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets>'
            .'<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">'
            .$this->escape("'".str_replace("'", "''", mb_substr($this->sheetName, 0, 31))."'!".$this->range()).'</definedName></definedNames>'
            .'</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        // Stílusok: 0 = alap, 1 = fejléc (félkövér, zöld háttér, fehér betű), 2 = dátum+idő, 3 = tördelt szöveg
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="yyyy\.mm\.dd\. hh:mm"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF245B48"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment vertical="top" horizontal="left"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml());
        $zip->close();

        $content = file_get_contents($path);
        @unlink($path);

        return $content;
    }

    private function sheetXml(): string
    {
        $cols = '';
        foreach ($this->columns as $i => $column) {
            $n = $i + 1;
            $cols .= '<col min="'.$n.'" max="'.$n.'" width="'.$column['width'].'" customWidth="1"/>';
        }

        $rows = '<row r="1">';
        foreach ($this->columns as $i => $column) {
            $rows .= $this->cell($i, 1, $column['title'], 1);
        }
        $rows .= '</row>';
        foreach ($this->rows as $r => $row) {
            $rowNumber = $r + 2;
            $rows .= '<row r="'.$rowNumber.'">';
            foreach ($row as $i => $value) {
                $rows .= $this->cell($i, $rowNumber, $value);
            }
            $rows .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$cols.'</cols>'
            .'<sheetData>'.$rows.'</sheetData>'
            .'<autoFilter ref="'.$this->range().'"/>'
            .'</worksheet>';
    }

    private function cell(int $col, int $row, string|int|float|CarbonInterface|null $value, ?int $style = null): string
    {
        $ref = $this->columnLetter($col).$row;
        if ($value === null || $value === '') {
            return '';
        }
        if ($value instanceof CarbonInterface) {
            // Excel-dátum: napok 1899-12-30 óta, az alkalmazás időzónájában
            $local = $value->copy()->setTimezone(config('app.timezone'));
            $serial = 25569 + ($local->getTimestamp() + $local->getOffset()) / 86400;

            return '<c r="'.$ref.'" s="2"><v>'.round($serial, 6).'</v></c>';
        }
        if (is_int($value) || is_float($value)) {
            return '<c r="'.$ref.'"'.($style ? ' s="'.$style.'"' : '').'><v>'.$value.'</v></c>';
        }
        $s = $style ?? (str_contains($value, "\n") || mb_strlen($value) > 60 ? 3 : 0);

        return '<c r="'.$ref.'" t="inlineStr"'.($s ? ' s="'.$s.'"' : '').'><is><t xml:space="preserve">'.$this->escape($value).'</t></is></c>';
    }

    private function range(): string
    {
        return 'A1:'.$this->columnLetter(max(count($this->columns) - 1, 0)).(count($this->rows) + 1);
    }

    private function columnLetter(int $index): string
    {
        $letters = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(65 + ($n - 1) % 26).$letters;
        }

        return $letters;
    }

    private function escape(string $text): string
    {
        // Az XML-ben tiltott vezérlőkarakterek kiszűrése
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
