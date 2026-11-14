<?php
declare(strict_types=1);

/**
 * Lightweight XLSX writer for Sokna exports.
 * Requires the common PHP ZipArchive extension; no Composer dependency.
 */
final class XlsxExporter
{
    private array $sheets = [];

    public function addSheet(string $name, array $rows, array $options = []): void
    {
        $name = $this->safeSheetName($name);
        $this->sheets[] = ['name' => $name, 'rows' => $rows, 'options' => $options];
    }

    public function save(string $path): void
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('افزونه ZipArchive روی سرور فعال نیست.');
        }
        if (!$this->sheets) {
            throw new RuntimeException('هیچ شیتی برای خروجی تعریف نشده است.');
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('پوشه ذخیره خروجی قابل ساخت نیست.');
        }
        $tmpPath = $path.'.tmp-'.bin2hex(random_bytes(5));
        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ساخت فایل اکسل ممکن نشد.');
        }
        try {
            $this->addPart($zip, '[Content_Types].xml', $this->contentTypes());
            $this->addPart($zip, '_rels/.rels', $this->rootRels());
            $this->addPart($zip, 'docProps/app.xml', $this->appProps());
            $this->addPart($zip, 'docProps/core.xml', $this->coreProps());
            $this->addPart($zip, 'xl/workbook.xml', $this->workbookXml());
            $this->addPart($zip, 'xl/_rels/workbook.xml.rels', $this->workbookRels());
            $this->addPart($zip, 'xl/styles.xml', $this->stylesXml());
            foreach ($this->sheets as $i => $sheet) {
                $this->addPart($zip, 'xl/worksheets/sheet'.($i + 1).'.xml', $this->sheetXml($sheet['rows'], $sheet['options']));
            }
            if (!$zip->close()) throw new RuntimeException('بستن فایل اکسل ممکن نشد.');
            $this->validatePackage($tmpPath);
            if (is_file($path) && !@unlink($path)) throw new RuntimeException('جایگزینی فایل قبلی ممکن نشد.');
            if (!@rename($tmpPath, $path)) throw new RuntimeException('انتقال فایل اکسل به آرشیو ممکن نشد.');
        } catch (Throwable $e) {
            @$zip->close();
            @unlink($tmpPath);
            throw $e;
        }
    }

    private function addPart(ZipArchive $zip, string $name, string $content): void
    {
        if ($content === '' || !preg_match('//u', $content) || !$zip->addFromString($name, $content)) {
            throw new RuntimeException('یکی از اجزای فایل اکسل به‌درستی ساخته نشد: '.$name);
        }
    }

    private function validatePackage(string $path): void
    {
        if (!is_file($path) || filesize($path) < 1000) throw new RuntimeException('فایل اکسل ناقص ساخته شد.');
        $required = ['[Content_Types].xml','_rels/.rels','docProps/app.xml','docProps/core.xml','xl/workbook.xml','xl/_rels/workbook.xml.rels','xl/styles.xml'];
        foreach ($this->sheets as $i => $_) $required[] = 'xl/worksheets/sheet'.($i + 1).'.xml';
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('بازخوانی فایل اکسل ساخته‌شده ممکن نشد.');
        try {
            foreach ($required as $entry) {
                $index = $zip->locateName($entry);
                if ($index === false) throw new RuntimeException('فایل اکسل ناقص است؛ بخش '.$entry.' وجود ندارد.');
                $content = $zip->getFromIndex($index);
                if (!is_string($content) || $content === '' || !preg_match('//u', $content) || !str_starts_with(ltrim($content), '<?xml')) {
                    throw new RuntimeException('ساختار داخلی فایل اکسل معتبر نیست: '.$entry);
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function safeSheetName(string $name): string
    {
        $name = preg_replace('~[\\/?*\[\]:]~u', ' ', trim($name)) ?: 'Sheet';
        $name = $this->utf8Substr($name, 0, 31);
        $base = $name;
        $n = 2;
        $existing = array_column($this->sheets, 'name');
        while (in_array($name, $existing, true)) {
            $suffix = ' '.$n++;
            $name = $this->utf8Substr($base, 0, 31 - $this->utf8Length($suffix)).$suffix;
        }
        return $name;
    }

    private function sheetXml(array $rows, array $o): string
    {
        $maxCol = 1;
        foreach ($rows as $r) {
            foreach (($r['cells'] ?? []) as $col => $_) $maxCol = max($maxCol, (int)$col);
        }
        $maxRow = max(1, count($rows));
        $cols = $o['columns'] ?? [];
        $colXml = '';
        foreach ($cols as $idx => $width) {
            $col = is_int($idx) ? $idx : (int)$idx;
            $colXml .= '<col min="'.$col.'" max="'.$col.'" width="'.max(4, min(70, (float)$width)).'" customWidth="1"/>';
        }
        $sheetData = '';
        foreach ($rows as $rIdx => $row) {
            $rn = $rIdx + 1;
            $height = isset($row['height']) ? ' ht="'.(float)$row['height'].'" customHeight="1"' : '';
            $hidden = !empty($row['hidden']) ? ' hidden="1"' : '';
            $sheetData .= '<row r="'.$rn.'"'.$height.$hidden.'>';
            ksort($row['cells']);
            foreach ($row['cells'] as $col => $cell) {
                $ref = $this->colName((int)$col).$rn;
                $style = (int)($cell['style'] ?? 0);
                $value = $cell['value'] ?? '';
                if (($cell['type'] ?? '') === 'number' && is_numeric($value)) {
                    $number=(string)$value;
                    // Excel numeric cells only retain 15 significant digits. For larger
                    // integer monetary amounts, preserve the exact value as text rather
                    // than silently changing the amount on opening/saving the workbook.
                    if (preg_match('/^-?[0-9]+$/D', $number) && strlen(ltrim($number, '-0')) > 15) {
                        $sheetData .= '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t>'.$this->xml($number).'</t></is></c>';
                    } else {
                        $sheetData .= '<c r="'.$ref.'" s="'.$style.'"><v>'.(0 + $value).'</v></c>';
                    }
                } elseif (($cell['type'] ?? '') === 'formula') {
                    $sheetData .= '<c r="'.$ref.'" s="'.$style.'"><f>'.$this->xml((string)$value).'</f></c>';
                } elseif ($value === '' || $value === null) {
                    $sheetData .= '<c r="'.$ref.'" s="'.$style.'"/>';
                } else {
                    $sheetData .= '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml((string)$value).'</t></is></c>';
                }
            }
            $sheetData .= '</row>';
        }
        $merges = $this->normalizeMerges($o['merges'] ?? []);
        $mergeXml = $merges ? '<mergeCells count="'.count($merges).'">'.implode('', array_map(fn($m) => '<mergeCell ref="'.$m.'"/>', $merges)).'</mergeCells>' : '';
        $freezeRows = max(0, (int)($o['freeze_rows'] ?? 0));
        $freezeCols = max(0, (int)($o['freeze_cols'] ?? 0));
        $pane = '';
        if ($freezeRows || $freezeCols) {
            $topLeft = $this->colName($freezeCols + 1).($freezeRows + 1);
            $activePane = $freezeRows && $freezeCols ? 'bottomRight' : ($freezeCols ? 'topRight' : 'bottomLeft');
            $pane = '<pane'.($freezeCols ? ' xSplit="'.$freezeCols.'"' : '').($freezeRows ? ' ySplit="'.$freezeRows.'"' : '').' topLeftCell="'.$topLeft.'" activePane="'.$activePane.'" state="frozen"/>';
        }
        $orientation = ($o['orientation'] ?? 'landscape') === 'portrait' ? 'portrait' : 'landscape';
        $autoFilter = trim((string)($o['auto_filter'] ?? ''));
        $autoFilterXml = preg_match('/^[A-Z]+[1-9][0-9]*:[A-Z]+[1-9][0-9]*$/', $autoFilter) ? '<autoFilter ref="'.$autoFilter.'"/>' : '';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<dimension ref="A1:'.$this->colName($maxCol).$maxRow.'"/>'
            .'<sheetViews><sheetView workbookViewId="0" rightToLeft="1" showGridLines="0">'.$pane.'</sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="20"/>'
            .($colXml ? '<cols>'.$colXml.'</cols>' : '')
            .'<sheetData>'.$sheetData.'</sheetData>'.$autoFilterXml.$mergeXml
            .'<printOptions horizontalCentered="1"/>'
            .'<pageMargins left="0.25" right="0.25" top="0.45" bottom="0.45" header="0.2" footer="0.2"/>'
            .'<pageSetup orientation="'.$orientation.'" fitToWidth="1" fitToHeight="0" paperSize="9"/>'
            .'</worksheet>';
    }

    private function normalizeMerges(array $ranges): array
    {
        $valid = [];
        foreach (array_unique(array_map('strval', $ranges)) as $range) {
            if (!preg_match('/^([A-Z]+)([1-9][0-9]*):([A-Z]+)([1-9][0-9]*)$/', strtoupper(trim($range)), $m)) continue;
            $candidate = [$this->colIndex($m[1]), (int)$m[2], $this->colIndex($m[3]), (int)$m[4]];
            if ($candidate[0] > $candidate[2] || $candidate[1] > $candidate[3]) continue;
            $overlaps = false;
            foreach ($valid as $entry) {
                if (!($candidate[2] < $entry['box'][0] || $candidate[0] > $entry['box'][2] || $candidate[3] < $entry['box'][1] || $candidate[1] > $entry['box'][3])) {
                    $overlaps = true;
                    break;
                }
            }
            if (!$overlaps) $valid[] = ['ref'=>strtoupper(trim($range)), 'box'=>$candidate];
        }
        return array_column($valid, 'ref');
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0 &quot;تومان&quot;"/><numFmt numFmtId="165" formatCode="0%"/></numFmts>'
        .'<fonts count="5">'
          .'<font><sz val="11"/><name val="Vazirmatn"/><family val="2"/><charset val="178"/></font>'
          .'<font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Vazirmatn"/><family val="2"/><charset val="178"/></font>'
          .'<font><b/><sz val="12"/><color rgb="FFFFFFFF"/><name val="Vazirmatn"/><family val="2"/><charset val="178"/></font>'
          .'<font><b/><sz val="11"/><color rgb="FF23251F"/><name val="Vazirmatn"/><family val="2"/><charset val="178"/></font>'
          .'<font><sz val="10"/><color rgb="FF5E685F"/><name val="Vazirmatn"/><family val="2"/><charset val="178"/></font>'
        .'</fonts>'
        .'<fills count="10">'
          .'<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
          .'<fill><patternFill patternType="solid"><fgColor rgb="FF31473A"/><bgColor indexed="64"/></patternFill></fill>'
          .'<fill><patternFill patternType="solid"><fgColor rgb="FFF1EEE7"/><bgColor indexed="64"/></patternFill></fill>'
          .'<fill><patternFill patternType="solid"><fgColor rgb="FFDCE9DF"/><bgColor indexed="64"/></patternFill></fill>'
          .'<fill><patternFill patternType="solid"><fgColor rgb="FFF4E6BF"/><bgColor indexed="64"/></patternFill></fill>'
          .'<fill><patternFill patternType="solid"><fgColor rgb="FFC8DDCD"/><bgColor indexed="64"/></patternFill></fill>'
          .'<fill><patternFill patternType="solid"><fgColor rgb="FFEADBD9"/><bgColor indexed="64"/></patternFill></fill>'
          .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFFDF9"/><bgColor indexed="64"/></patternFill></fill>'
          .'<fill><patternFill patternType="solid"><fgColor rgb="FFE9DFC9"/><bgColor indexed="64"/></patternFill></fill>'
        .'</fills>'
        .'<borders count="3">'
          .'<border><left/><right/><top/><bottom/><diagonal/></border>'
          .'<border><left style="thin"><color rgb="FFDED8CE"/></left><right style="thin"><color rgb="FFDED8CE"/></right><top style="thin"><color rgb="FFDED8CE"/></top><bottom style="thin"><color rgb="FFDED8CE"/></bottom><diagonal/></border>'
          .'<border><left style="medium"><color rgb="FF73947C"/></left><right style="medium"><color rgb="FF73947C"/></right><top style="medium"><color rgb="FF73947C"/></top><bottom style="medium"><color rgb="FF73947C"/></bottom><diagonal/></border>'
        .'</borders>'
        .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        .'<cellXfs count="18">'
          .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="right"/></xf>' //0
          .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center"/></xf>' //1 title
          .'<xf numFmtId="0" fontId="4" fillId="3" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center"/></xf>' //2 subtitle
          .'<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center" wrapText="1"/></xf>' //3 header
          .'<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="right" wrapText="1"/></xf>' //4 room
          .'<xf numFmtId="0" fontId="0" fillId="8" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center" wrapText="1"/></xf>' //5 free
          .'<xf numFmtId="0" fontId="3" fillId="4" borderId="2" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center" wrapText="1"/></xf>' //6 confirmed
          .'<xf numFmtId="0" fontId="3" fillId="5" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center" wrapText="1"/></xf>' //7 hold
          .'<xf numFmtId="0" fontId="3" fillId="6" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center" wrapText="1"/></xf>' //8 checked in
          .'<xf numFmtId="0" fontId="3" fillId="7" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center" wrapText="1"/></xf>' //9 service
          .'<xf numFmtId="164" fontId="0" fillId="8" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="center" horizontal="center"/></xf>' //10 money
          .'<xf numFmtId="0" fontId="3" fillId="9" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center" wrapText="1"/></xf>' //11 detail header
          .'<xf numFmtId="0" fontId="0" fillId="8" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="right" wrapText="1"/></xf>' //12 detail
          .'<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center" wrapText="1"/></xf>' //13 summary label
          .'<xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center" wrapText="1"/></xf>' //14 summary value
          .'<xf numFmtId="165" fontId="3" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="center" horizontal="center"/></xf>' //15 percent
          .'<xf numFmtId="0" fontId="4" fillId="8" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="right" wrapText="1"/></xf>' //16 note
          .'<xf numFmtId="0" fontId="0" fillId="8" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="center"/></xf>' //17 centered
        .'</cellXfs>'
        .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        .'</styleSheet>';
    }

    private function contentTypes(): string
    {
        $sheets = '';
        foreach ($this->sheets as $i => $_) $sheets .= '<Override PartName="/xl/worksheets/sheet'.($i + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$sheets
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        $s = '';
        foreach ($this->sheets as $i => $sheet) $s .= '<sheet name="'.$this->xml($sheet['name']).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><workbookPr date1904="0"/><bookViews><workbookView activeTab="0"/></bookViews><sheets>'.$s.'</sheets><calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>';
    }

    private function workbookRels(): string
    {
        $rels = '';
        foreach ($this->sheets as $i => $_) $rels .= '<Relationship Id="rId'.($i + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($i + 1).'.xml"/>';
        $n = count($this->sheets) + 1;
        $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }

    private function coreProps(): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>گزارش رزرو Sokna</dc:title><dc:creator>Sokna</dc:creator><cp:lastModifiedBy>Sokna</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:modified></cp:coreProperties>';
    }

    private function appProps(): string
    {
        $names = implode('', array_map(fn($s) => '<vt:lpstr>'.$this->xml($s['name']).'</vt:lpstr>', $this->sheets));
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Sokna</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop><HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>'.count($this->sheets).'</vt:i4></vt:variant></vt:vector></HeadingPairs><TitlesOfParts><vt:vector size="'.count($this->sheets).'" baseType="lpstr">'.$names.'</vt:vector></TitlesOfParts></Properties>';
    }

    private function colName(int $n): string
    {
        $s = '';
        while ($n > 0) { $n--; $s = chr(65 + ($n % 26)).$s; $n = intdiv($n, 26); }
        return $s ?: 'A';
    }

    private function colIndex(string $name): int
    {
        $n = 0;
        foreach (str_split(strtoupper($name)) as $char) $n = $n * 26 + (ord($char) - 64);
        return $n;
    }

    private function utf8Length(string $value): int
    {
        if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
        return count(preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    private function utf8Substr(string $value, int $start, int $length): string
    {
        if (function_exists('mb_substr')) return mb_substr($value, $start, $length, 'UTF-8');
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode('', array_slice($chars, $start, $length));
    }

    private function xml(string $s): string
    {
        if (!preg_match('//u', $s)) $s = (string)@iconv('UTF-8', 'UTF-8//IGNORE', $s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
        $s = preg_replace('/\x{FFFE}|\x{FFFF}/u', '', $s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
