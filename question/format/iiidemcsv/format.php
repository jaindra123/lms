<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * IIIDEM spreadsheet CSV importer for multiple choice questions.
 *
 * Expected columns (header row):
 * SR, Question, Option1, IsOption1, Option2, IsOption2, … Option5, IsOption5
 *
 * @package    qformat_iiidemcsv
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Import multichoice questions from IIIDEM Excel/CSV layout.
 */
class qformat_iiidemcsv extends qformat_default {

    /** @var int Maximum option columns supported. */
    protected const MAXOPTIONS = 5;

    public function provide_import(): bool {
        return true;
    }

    public function provide_export(): bool {
        return false;
    }

    public function export_file_extension(): string {
        return '.csv';
    }

    public function mime_type(): string {
        return 'text/csv';
    }

    public function can_import_file($file): bool {
        $allowed = [
            'text/csv',
            'text/plain',
            'application/csv',
            'text/comma-separated-values',
            'application/vnd.ms-excel',
        ];
        return in_array($file->get_mimetype(), $allowed, true);
    }

    public function validate_file(\stored_file $file): string {
        $utf8error = $this->validate_is_utf8_file($file);
        if ($utf8error !== '') {
            return $utf8error;
        }

        $content = $file->get_content();
        $lines = preg_split('/\r\n|\r|\n/', $content, 2);
        if (empty($lines[0])) {
            return get_string('missingquestioncolumn', 'qformat_iiidemcsv');
        }

        $headers = $this->parse_csv_line($lines[0]);
        if (!$this->find_column_index($headers, 'question')) {
            return get_string('missingquestioncolumn', 'qformat_iiidemcsv');
        }

        return '';
    }

    /**
     * @param array $lines Unused; file is read from {@see $this->filename}.
     * @return array
     */
    public function readquestions($lines) {
        unset($lines);

        if (empty($this->filename) || !is_readable($this->filename)) {
            return [];
        }

        $handle = fopen($this->filename, 'r');
        if ($handle === false) {
            return [];
        }

        $headerrow = fgetcsv($handle);
        if ($headerrow === false) {
            fclose($handle);
            $this->error(get_string('missingquestioncolumn', 'qformat_iiidemcsv'));
            return [];
        }

        $headerrow[0] = core_text::trim_utf8_bom($headerrow[0]);
        $colmap = $this->build_column_map($headerrow);
        if ($colmap['question'] === null) {
            fclose($handle);
            $this->error(get_string('missingquestioncolumn', 'qformat_iiidemcsv'));
            return [];
        }

        $questions = [];
        $rownum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rownum++;
            if ($this->is_empty_row($row)) {
                continue;
            }

            $q = $this->parse_row($row, $colmap, $rownum);
            if ($q !== null) {
                $questions[] = $q;
            }
        }

        fclose($handle);

        if (empty($questions)) {
            $this->error(get_string('norows', 'qformat_iiidemcsv'));
        }

        return $questions;
    }

    /**
     * @param string $line
     * @return array
     */
    protected function parse_csv_line(string $line): array {
        return str_getcsv($line);
    }

    /**
     * @param array $headers
     * @return array
     */
    protected function build_column_map(array $headers): array {
        $map = [
            'question' => $this->find_column_index($headers, 'question'),
            'options' => [],
        ];

        for ($i = 1; $i <= self::MAXOPTIONS; $i++) {
            $optindex = $this->find_column_index($headers, 'option' . $i);
            $flagindex = $this->find_column_index($headers, 'isoption' . $i);
            if ($optindex !== null) {
                $map['options'][$i] = [
                    'text' => $optindex,
                    'flag' => $flagindex,
                ];
            }
        }

        return $map;
    }

    /**
     * @param array $headers
     * @param string $name
     * @return int|null
     */
    protected function find_column_index(array $headers, string $name): ?int {
        $target = $this->normalize_header($name);
        foreach ($headers as $index => $header) {
            if ($this->normalize_header((string) $header) === $target) {
                return (int) $index;
            }
        }
        return null;
    }

    /**
     * @param string $header
     * @return string
     */
    protected function normalize_header(string $header): string {
        return strtolower(preg_replace('/[^a-z0-9]/', '', $header));
    }

    /**
     * @param array $row
     * @return bool
     */
    protected function is_empty_row(array $row): bool {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array $row
     * @param array $colmap
     * @param int $rownum
     * @return \stdClass|null
     */
    protected function parse_row(array $row, array $colmap, int $rownum): ?\stdClass {
        $questiontext = $this->cell($row, $colmap['question']);
        if ($questiontext === '') {
            $this->error(get_string('rowmissingquestion', 'qformat_iiidemcsv', $rownum));
            return null;
        }

        $answers = [];
        $fractions = [];
        $correctcount = 0;

        foreach ($colmap['options'] as $optiondef) {
            $text = $this->cell($row, $optiondef['text']);
            if ($text === '') {
                continue;
            }

            $iscorrect = false;
            if ($optiondef['flag'] !== null) {
                $iscorrect = $this->parse_bool($this->cell($row, $optiondef['flag']));
            }

            $answers[] = $this->text_field($text);
            $fractions[] = $iscorrect ? 1 : 0;
            if ($iscorrect) {
                $correctcount++;
            }
        }

        if (count($answers) < 2) {
            $this->error(get_string('rowfewoptions', 'qformat_iiidemcsv', $rownum));
            return null;
        }

        if ($correctcount === 0) {
            $this->error(get_string('rownocorrect', 'qformat_iiidemcsv', $rownum));
            return null;
        }

        if ($correctcount > 1) {
            $share = 1.0 / $correctcount;
            foreach ($fractions as $idx => $fraction) {
                if ($fraction > 0) {
                    $fractions[$idx] = $share;
                }
            }
        }

        $question = $this->defaultquestion();
        $question->qtype = 'multichoice';
        $question->name = $this->create_default_question_name($questiontext, get_string('questionname', 'question'));
        $question->questiontext = htmlspecialchars($questiontext, ENT_NOQUOTES);
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = '';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->single = ($correctcount === 1) ? 1 : 0;
        $question->answer = $answers;
        $question->fraction = $fractions;
        $question->feedback = array_fill(0, count($answers), $this->text_field(''));
        $question->correctfeedback = $this->text_field('');
        $question->partiallycorrectfeedback = $this->text_field('');
        $question->incorrectfeedback = $this->text_field('');

        return $question;
    }

    /**
     * @param array $row
     * @param int|null $index
     * @return string
     */
    protected function cell(array $row, ?int $index): string {
        if ($index === null || !array_key_exists($index, $row)) {
            return '';
        }
        return trim((string) $row[$index]);
    }

    /**
     * @param string $value
     * @return bool
     */
    protected function parse_bool(string $value): bool {
        $value = strtolower(trim($value));
        return in_array($value, ['true', '1', 'yes', 'y'], true);
    }

    /**
     * @param string $text
     * @return array
     */
    protected function text_field(string $text): array {
        return [
            'text' => htmlspecialchars(trim($text), ENT_NOQUOTES),
            'format' => FORMAT_HTML,
            'files' => [],
        ];
    }
}
