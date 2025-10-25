<?php

namespace App\Services;

class ViewRenderer
{
    private string $viewsBasePath;

    public function __construct(?string $viewsBasePath = null)
    {
        // app/Services -> up 1: Services, up 2: app, join views
        $this->viewsBasePath = $viewsBasePath ?? (dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . 'views');
    }

    public function render(string $template, array $data = []): void
    {
        $path = $this->resolvePath($template);
        if (!file_exists($path)) {
            throw new \RuntimeException("View not found: {$path}");
        }
        extract($data);
        include_once $path;
    }

    public function renderToString(string $template, array $data = []): string
    {
        ob_start();
        $this->render($template, $data);
        return (string)ob_get_clean();
    }

    private function resolvePath(string $template): string
    {
        // Allow callers to pass either with or without .php
        $filename = str_ends_with($template, '.php') ? $template : ($template . '.php');
        return $this->viewsBasePath . DIRECTORY_SEPARATOR . $filename;
    }
}

