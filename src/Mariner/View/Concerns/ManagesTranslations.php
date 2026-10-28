<?php

namespace Mariner\View\Concerns;

trait ManagesTranslations
{

    /**
     * Start a translation block.
     *
     * @param  array  $replacements
     * @return void
     */
    public function startTranslation($replacements = [])
    {
        ob_start();

        $this->renderState()->translationReplacements = $replacements;
    }

    /**
     * Render the current translation.
     *
     * @return string
     */
    public function renderTranslation()
    {
        return $this->container->make('translator')->get(
            trim(ob_get_clean()), $this->renderState()->translationReplacements
        );
    }
}
