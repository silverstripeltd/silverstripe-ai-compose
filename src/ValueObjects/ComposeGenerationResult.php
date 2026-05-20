<?php

namespace SilverstripeLtd\AiCompose\ValueObjects;

/**
 * Carries one generated title and content pair.
 */
class ComposeGenerationResult
{
    private string $title;

    private string $content;

    /**
     * Create one validated generation result.
     */
    public function __construct(string $title, string $content)
    {
        $this->title = $title;
        $this->content = $content;
    }

    /**
     * Returns the generated title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Returns the generated content HTML.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Returns the result as a serialisable array.
     *
     * @return array{title: string, content: string}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
        ];
    }
}
