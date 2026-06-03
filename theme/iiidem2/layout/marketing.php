<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Marketing / static content pages (about-us, etc.).
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$template = theme_iiidem2_get_marketing_template();
if ($template === '') {
    throw new moodle_exception('marketingtemplaterequired', 'theme_iiidem2');
}

$templatecontext = theme_iiidem2_merge_footer_context(array_merge(
    theme_iiidem2_get_marketing_page_context(),
    theme_iiidem2_get_marketing_extra_context()
));

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <?php echo $OUTPUT->standard_head_html(); ?>
</head>
<body <?php echo $OUTPUT->body_attributes(['pagelayout-marketing']); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<?php
echo $OUTPUT->render_from_template($template, $templatecontext);
?>
</body>
</html>
