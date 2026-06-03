<?php

class __Mustache_b3a3857ad54356e916713a54aaeb2b5c extends Mustache_Template
{
    public function renderInternal(Mustache_Context $context, $indent = '')
    {
        $buffer = '';

        if ($partial = $this->mustache->loadPartial('theme_iiidem2/footer')) {
            $buffer .= $partial->renderInternal($context);
        }
        $value = $this->resolveValue($context->findDot('output.standard_after_main_region_html'), $context);
        $buffer .= $indent . ($value === null ? '' : $value);
        $buffer .= '
';

        return $buffer;
    }
}
