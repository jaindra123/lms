<?php

class __Mustache_4500456e7578b6eddfa74b79c7c6d58c extends Mustache_Template
{
    private $lambdaHelper;

    public function renderInternal(Mustache_Context $context, $indent = '')
    {
        $this->lambdaHelper = new Mustache_LambdaHelper($this->mustache, $context);
        $buffer = '';

        if ($partial = $this->mustache->loadPartial('theme_iiidem2/navbar')) {
            $buffer .= $partial->renderInternal($context);
        }
        $buffer .= $indent . '
';
        $buffer .= $indent . '<main class="iiidem-frontpage" id="iiidem-frontpage-main">
';
        $buffer .= $indent . '
';
        if ($partial = $this->mustache->loadPartial('theme_iiidem2/slider')) {
            $buffer .= $partial->renderInternal($context, $indent . '    ');
        }
        $buffer .= $indent . '
';
        $buffer .= $indent . '    <section class="iiidem-features" aria-labelledby="iiidem-features-heading">
';
        $buffer .= $indent . '        <div class="container">
';
        $buffer .= $indent . '            <h2 id="iiidem-features-heading" class="visually-hidden">';
        $value = $context->find('str');
        $buffer .= $this->section3939f856c5f174dc142120fb790c1ac7($context, $indent, $value);
        $buffer .= '</h2>
';
        $buffer .= $indent . '            <div class="row g-4">
';
        $buffer .= $indent . '                <div class="col-md-4">
';
        $buffer .= $indent . '                    <div class="iiidem-feature-card">
';
        $buffer .= $indent . '                        <div class="iiidem-feature-card__icon" aria-hidden="true">
';
        $buffer .= $indent . '                            <i class="fa-solid fa-graduation-cap"></i>
';
        $buffer .= $indent . '                        </div>
';
        $buffer .= $indent . '                        <h3 class="iiidem-feature-card__title">Certification Programs</h3>
';
        $buffer .= $indent . '                        <p class="iiidem-feature-card__text">Structured courses with assessments, progress tracking, and credentials.</p>
';
        $buffer .= $indent . '                    </div>
';
        $buffer .= $indent . '                </div>
';
        $buffer .= $indent . '                <div class="col-md-4">
';
        $buffer .= $indent . '                    <div class="iiidem-feature-card">
';
        $buffer .= $indent . '                        <div class="iiidem-feature-card__icon" aria-hidden="true">
';
        $buffer .= $indent . '                            <i class="fa-solid fa-video"></i>
';
        $buffer .= $indent . '                        </div>
';
        $buffer .= $indent . '                        <h3 class="iiidem-feature-card__title">Live &amp; Recorded Learning</h3>
';
        $buffer .= $indent . '                        <p class="iiidem-feature-card__text">Join live sessions or learn at your own pace with rich multimedia content.</p>
';
        $buffer .= $indent . '                    </div>
';
        $buffer .= $indent . '                </div>
';
        $buffer .= $indent . '                <div class="col-md-4">
';
        $buffer .= $indent . '                    <div class="iiidem-feature-card">
';
        $buffer .= $indent . '                        <div class="iiidem-feature-card__icon" aria-hidden="true">
';
        $buffer .= $indent . '                            <i class="fa-solid fa-certificate"></i>
';
        $buffer .= $indent . '                        </div>
';
        $buffer .= $indent . '                        <h3 class="iiidem-feature-card__title">Track Your Progress</h3>
';
        $buffer .= $indent . '                        <p class="iiidem-feature-card__text">Dashboards, completion status, and certificates in one place.</p>
';
        $buffer .= $indent . '                    </div>
';
        $buffer .= $indent . '                </div>
';
        $buffer .= $indent . '            </div>
';
        $buffer .= $indent . '        </div>
';
        $buffer .= $indent . '    </section>
';
        $buffer .= $indent . '
';
        $buffer .= $indent . '    <section class="iiidem-about-section" id="about-iiidem">
';
        if ($partial = $this->mustache->loadPartial('theme_iiidem2/about')) {
            $buffer .= $partial->renderInternal($context, $indent . '        ');
        }
        $buffer .= $indent . '    </section>
';
        $buffer .= $indent . '
';
        $value = $context->find('hascourses');
        $buffer .= $this->section82c922986b287e845e478c35ec571577($context, $indent, $value);
        $buffer .= $indent . '
';
        $buffer .= $indent . '    <section class="iiidem-cta">
';
        $buffer .= $indent . '        <div class="container">
';
        $buffer .= $indent . '            <div class="iiidem-cta__inner text-center">
';
        $buffer .= $indent . '                <h2 class="iiidem-cta__title">Ready to start learning?</h2>
';
        $buffer .= $indent . '                <p class="iiidem-cta__text">Sign in to access your courses, live classes, and certificates.</p>
';
        $buffer .= $indent . '                <div class="iiidem-cta__actions">
';
        $value = $context->find('isrealuser');
        $buffer .= $this->section271fbe9a355c0b0582fc7c11b4b3bb0e($context, $indent, $value);
        $value = $context->find('isrealuser');
        if (empty($value)) {
            
            $buffer .= $indent . '                        <a href="';
            $value = $this->resolveValue($context->find('loginurl'), $context);
            $buffer .= ($value === null ? '' : call_user_func($this->mustache->getEscape(), $value));
            $buffer .= '" class="btn btn-light btn-lg iiidem-btn">Login to portal</a>
';
            $buffer .= $indent . '                        <a href="';
            $value = $this->resolveValue($context->find('coursesurl'), $context);
            $buffer .= ($value === null ? '' : call_user_func($this->mustache->getEscape(), $value));
            $buffer .= '" class="btn btn-outline-light btn-lg iiidem-btn iiidem-btn--outline">Browse courses</a>
';
        }
        $buffer .= $indent . '                </div>
';
        $buffer .= $indent . '            </div>
';
        $buffer .= $indent . '        </div>
';
        $buffer .= $indent . '    </section>
';
        $buffer .= $indent . '
';
        $buffer .= $indent . '</main>
';
        $buffer .= $indent . '
';
        $buffer .= $indent . '<div class="d-none" aria-hidden="true">
';
        $buffer .= $indent . '    ';
        $value = $this->resolveValue($context->findDot('output.main_content'), $context);
        $buffer .= ($value === null ? '' : $value);
        $buffer .= '
';
        $buffer .= $indent . '</div>
';
        $buffer .= $indent . '
';
        if ($partial = $this->mustache->loadPartial('theme_iiidem2/page_end')) {
            $buffer .= $partial->renderInternal($context);
        }

        return $buffer;
    }

    private function section3939f856c5f174dc142120fb790c1ac7(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = 'home';
            $result = (string) call_user_func($value, $source, $this->lambdaHelper);
            $buffer .= $result;
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= 'home';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function section82c922986b287e845e478c35ec571577(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
    <section class="iiidem-courses-section" id="courses">
        <div class="container">
            <div class="iiidem-section-header text-center">
                <span class="iiidem-section-header__eyebrow">Learning paths</span>
                <h2 class="iiidem-section-header__title">Explore Our Courses</h2>
                <p class="iiidem-section-header__lead">Browse certification and training programs designed for election professionals.</p>
            </div>
            {{> theme_iiidem2/courses }}
        </div>
    </section>
    ';
            $result = (string) call_user_func($value, $source, $this->lambdaHelper);
            $buffer .= $result;
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '    <section class="iiidem-courses-section" id="courses">
';
                $buffer .= $indent . '        <div class="container">
';
                $buffer .= $indent . '            <div class="iiidem-section-header text-center">
';
                $buffer .= $indent . '                <span class="iiidem-section-header__eyebrow">Learning paths</span>
';
                $buffer .= $indent . '                <h2 class="iiidem-section-header__title">Explore Our Courses</h2>
';
                $buffer .= $indent . '                <p class="iiidem-section-header__lead">Browse certification and training programs designed for election professionals.</p>
';
                $buffer .= $indent . '            </div>
';
                if ($partial = $this->mustache->loadPartial('theme_iiidem2/courses')) {
                    $buffer .= $partial->renderInternal($context, $indent . '            ');
                }
                $buffer .= $indent . '        </div>
';
                $buffer .= $indent . '    </section>
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function section271fbe9a355c0b0582fc7c11b4b3bb0e(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
                        <a href="{{coursesurl}}" class="btn btn-light btn-lg iiidem-btn">My courses</a>
                    ';
            $result = (string) call_user_func($value, $source, $this->lambdaHelper);
            $buffer .= $result;
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '                        <a href="';
                $value = $this->resolveValue($context->find('coursesurl'), $context);
                $buffer .= ($value === null ? '' : call_user_func($this->mustache->getEscape(), $value));
                $buffer .= '" class="btn btn-light btn-lg iiidem-btn">My courses</a>
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

}
