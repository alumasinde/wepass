<?php

namespace App\Core;

class View
{
    public static function render(string $view, array $data = [], ?string $layout = null)
    {
        extract($data);

        // -------------------------
        // Resolve module view
        // -------------------------
   if (strpos($view, '::') !== false) {
    [$module, $viewName] = explode('::', $view);

    // convert dot notation to folders
    $viewName = str_replace('.', '/', $viewName);

    $viewPath = base_path("src/Modules/{$module}/Views/{$viewName}.php");
} else {
    $view = str_replace('.', '/', $view);
    $viewPath = base_path("resources/views/{$view}.php");
}

if (!file_exists($viewPath)) {
    throw new \Exception("View not found: {$view}");
}

        // -------------------------
        // Capture view output
        // -------------------------
        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        // -------------------------
        // If no layout requested
        // -------------------------
        if (!$layout) {
            echo $content;
            return;
        }

        // -------------------------
        // Resolve layout path
        // -------------------------
        $layoutPath = base_path("resources/views/layouts/{$layout}.php");

        if (!file_exists($layoutPath)) {
            throw new \Exception("Layout not found: {$layout}");
        }

        require $layoutPath;
    }
}
