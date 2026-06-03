<?php

class __Mustache_3eba35d0f771aedba3ec97d61c15a20f extends Mustache_Template
{
    private $lambdaHelper;

    public function renderInternal(Mustache_Context $context, $indent = '')
    {
        $this->lambdaHelper = new Mustache_LambdaHelper($this->mustache, $context);
        $buffer = '';

        $buffer .= $indent . '<div id="homepageSlider"
';
        $buffer .= $indent . '     class="carousel slide iiidem-hero-carousel"
';
        $buffer .= $indent . '     data-interval="5000"
';
        $buffer .= $indent . '     data-pause="false"
';
        $buffer .= $indent . '     data-wrap="true"
';
        $buffer .= $indent . '     aria-label="Homepage highlights">
';
        $buffer .= $indent . '
';
        $buffer .= $indent . '    <ol class="carousel-indicators">
';
        $value = $context->find('slides');
        $buffer .= $this->section30e4c92804b8a905528115940063e2d3($context, $indent, $value);
        $buffer .= $indent . '    </ol>
';
        $buffer .= $indent . '
';
        $buffer .= $indent . '    <div class="carousel-inner">
';
        $value = $context->find('slides');
        $buffer .= $this->sectionC4bac8188e4457be270623b5434ac102($context, $indent, $value);
        $buffer .= $indent . '    </div>
';
        $buffer .= $indent . '
';
        $buffer .= $indent . '    <a class="carousel-control-prev" href="#homepageSlider" role="button" data-slide="prev">
';
        $buffer .= $indent . '        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
';
        $buffer .= $indent . '        <span class="sr-only">Previous</span>
';
        $buffer .= $indent . '    </a>
';
        $buffer .= $indent . '    <a class="carousel-control-next" href="#homepageSlider" role="button" data-slide="next">
';
        $buffer .= $indent . '        <span class="carousel-control-next-icon" aria-hidden="true"></span>
';
        $buffer .= $indent . '        <span class="sr-only">Next</span>
';
        $buffer .= $indent . '    </a>
';
        $buffer .= $indent . '</div>
';
        $value = $context->find('js');
        $buffer .= $this->sectionE9de70bd99539535654b7dcede86ac47($context, $indent, $value);

        return $buffer;
    }

    private function section5749c750acb0d7477dd5257d00cc6d53(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = 'active';
            $result = (string) call_user_func($value, $source, $this->lambdaHelper);
            $buffer .= $result;
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= 'active';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function section30e4c92804b8a905528115940063e2d3(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
        <li data-target="#homepageSlider"
            data-slide-to="{{index}}"
            class="{{#active}}active{{/active}}"></li>
        ';
            $result = (string) call_user_func($value, $source, $this->lambdaHelper);
            $buffer .= $result;
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '        <li data-target="#homepageSlider"
';
                $buffer .= $indent . '            data-slide-to="';
                $value = $this->resolveValue($context->find('index'), $context);
                $buffer .= ($value === null ? '' : call_user_func($this->mustache->getEscape(), $value));
                $buffer .= '"
';
                $buffer .= $indent . '            class="';
                $value = $context->find('active');
                $buffer .= $this->section5749c750acb0d7477dd5257d00cc6d53($context, $indent, $value);
                $buffer .= '"></li>
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionF77c513fbc6de10f32d675e9936d715d(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
                        <div class="iiidem-hero-carousel__desc">{{{description}}}</div>
                        ';
            $result = (string) call_user_func($value, $source, $this->lambdaHelper);
            $buffer .= $result;
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '                        <div class="iiidem-hero-carousel__desc">';
                $value = $this->resolveValue($context->find('description'), $context);
                $buffer .= ($value === null ? '' : $value);
                $buffer .= '</div>
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function section182f1cc99abdf4a658d916b7eb6be796(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
                        <a href="{{buttonurl}}" class="btn btn-light btn-lg iiidem-btn mt-3">{{buttontext}}</a>
                        ';
            $result = (string) call_user_func($value, $source, $this->lambdaHelper);
            $buffer .= $result;
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '                        <a href="';
                $value = $this->resolveValue($context->find('buttonurl'), $context);
                $buffer .= ($value === null ? '' : call_user_func($this->mustache->getEscape(), $value));
                $buffer .= '" class="btn btn-light btn-lg iiidem-btn mt-3">';
                $value = $this->resolveValue($context->find('buttontext'), $context);
                $buffer .= ($value === null ? '' : call_user_func($this->mustache->getEscape(), $value));
                $buffer .= '</a>
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionC4bac8188e4457be270623b5434ac102(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
        <div class="carousel-item {{#active}}active{{/active}}">
            <img src="{{image}}"
                 class="d-block w-100 iiidem-hero-carousel__image"
                 alt="{{title}}">
            <div class="carousel-caption iiidem-hero-carousel__caption">
               <!--  <div class="container">
                    <div class="iiidem-hero-carousel__caption-inner">
                        <h1 class="iiidem-hero-carousel__title">{{title}}</h1>
                        {{#description}}
                        <div class="iiidem-hero-carousel__desc">{{{description}}}</div>
                        {{/description}}
                        {{#buttontext}}
                        <a href="{{buttonurl}}" class="btn btn-light btn-lg iiidem-btn mt-3">{{buttontext}}</a>
                        {{/buttontext}}
                    </div>
                </div> -->
            </div>
        </div>
        ';
            $result = (string) call_user_func($value, $source, $this->lambdaHelper);
            $buffer .= $result;
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '        <div class="carousel-item ';
                $value = $context->find('active');
                $buffer .= $this->section5749c750acb0d7477dd5257d00cc6d53($context, $indent, $value);
                $buffer .= '">
';
                $buffer .= $indent . '            <img src="';
                $value = $this->resolveValue($context->find('image'), $context);
                $buffer .= ($value === null ? '' : call_user_func($this->mustache->getEscape(), $value));
                $buffer .= '"
';
                $buffer .= $indent . '                 class="d-block w-100 iiidem-hero-carousel__image"
';
                $buffer .= $indent . '                 alt="';
                $value = $this->resolveValue($context->find('title'), $context);
                $buffer .= ($value === null ? '' : call_user_func($this->mustache->getEscape(), $value));
                $buffer .= '">
';
                $buffer .= $indent . '            <div class="carousel-caption iiidem-hero-carousel__caption">
';
                $buffer .= $indent . '               <!--  <div class="container">
';
                $buffer .= $indent . '                    <div class="iiidem-hero-carousel__caption-inner">
';
                $buffer .= $indent . '                        <h1 class="iiidem-hero-carousel__title">';
                $value = $this->resolveValue($context->find('title'), $context);
                $buffer .= ($value === null ? '' : call_user_func($this->mustache->getEscape(), $value));
                $buffer .= '</h1>
';
                $value = $context->find('description');
                $buffer .= $this->sectionF77c513fbc6de10f32d675e9936d715d($context, $indent, $value);
                $value = $context->find('buttontext');
                $buffer .= $this->section182f1cc99abdf4a658d916b7eb6be796($context, $indent, $value);
                $buffer .= $indent . '                    </div>
';
                $buffer .= $indent . '                </div> -->
';
                $buffer .= $indent . '            </div>
';
                $buffer .= $indent . '        </div>
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionE9de70bd99539535654b7dcede86ac47(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
require([\'theme_iiidem2/frontpage_slider\'], function(slider) {
    slider.init();
});
';
            $result = (string) call_user_func($value, $source, $this->lambdaHelper);
            $buffer .= $result;
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . 'require([\'theme_iiidem2/frontpage_slider\'], function(slider) {
';
                $buffer .= $indent . '    slider.init();
';
                $buffer .= $indent . '});
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

}
