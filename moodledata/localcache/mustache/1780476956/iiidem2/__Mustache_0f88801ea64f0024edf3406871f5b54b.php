<?php

class __Mustache_0f88801ea64f0024edf3406871f5b54b extends Mustache_Template
{
    private $lambdaHelper;

    public function renderInternal(Mustache_Context $context, $indent = '')
    {
        $this->lambdaHelper = new Mustache_LambdaHelper($this->mustache, $context);
        $buffer = '';

        $buffer .= $indent . '<div class="iiidem-course-grid row g-4">
';
        $value = $context->find('courses');
        $buffer .= $this->section1c341dcc20678f06ba420e0bf0cbcf93($context, $indent, $value);
        $buffer .= $indent . '</div>
';

        return $buffer;
    }

    private function section1c341dcc20678f06ba420e0bf0cbcf93(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
    <div class="col-sm-6 col-lg-4">
        <article class="iiidem-course-card card h-100 border-0 shadow-sm">
            <div class="iiidem-course-card__media">
                <img src="{{courseimage}}" class="iiidem-course-card__image" alt="">
            </div>
            <div class="card-body d-flex flex-column">
                <h3 class="iiidem-course-card__title h5">{{fullname}}</h3>
                <p class="iiidem-course-card__summary text-muted flex-grow-1">{{summary}}</p>
                <a href="{{viewurl}}" class="btn btn-primary iiidem-course-card__btn align-self-start">
                    View course
                </a>
            </div>
        </article>
    </div>
    ';
            $result = (string) call_user_func($value, $source, $this->lambdaHelper);
            $buffer .= $result;
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '    <div class="col-sm-6 col-lg-4">
';
                $buffer .= $indent . '        <article class="iiidem-course-card card h-100 border-0 shadow-sm">
';
                $buffer .= $indent . '            <div class="iiidem-course-card__media">
';
                $buffer .= $indent . '                <img src="';
                $value = $this->resolveValue($context->find('courseimage'), $context);
                $buffer .= ($value === null ? '' : call_user_func($this->mustache->getEscape(), $value));
                $buffer .= '" class="iiidem-course-card__image" alt="">
';
                $buffer .= $indent . '            </div>
';
                $buffer .= $indent . '            <div class="card-body d-flex flex-column">
';
                $buffer .= $indent . '                <h3 class="iiidem-course-card__title h5">';
                $value = $this->resolveValue($context->find('fullname'), $context);
                $buffer .= ($value === null ? '' : call_user_func($this->mustache->getEscape(), $value));
                $buffer .= '</h3>
';
                $buffer .= $indent . '                <p class="iiidem-course-card__summary text-muted flex-grow-1">';
                $value = $this->resolveValue($context->find('summary'), $context);
                $buffer .= ($value === null ? '' : call_user_func($this->mustache->getEscape(), $value));
                $buffer .= '</p>
';
                $buffer .= $indent . '                <a href="';
                $value = $this->resolveValue($context->find('viewurl'), $context);
                $buffer .= ($value === null ? '' : call_user_func($this->mustache->getEscape(), $value));
                $buffer .= '" class="btn btn-primary iiidem-course-card__btn align-self-start">
';
                $buffer .= $indent . '                    View course
';
                $buffer .= $indent . '                </a>
';
                $buffer .= $indent . '            </div>
';
                $buffer .= $indent . '        </article>
';
                $buffer .= $indent . '    </div>
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

}
