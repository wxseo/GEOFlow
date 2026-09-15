<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class ArticleAiQualityRuntimeException extends RuntimeException
{
    private readonly ?string $validationCode;

    public function __construct(
        private readonly string $safeCode,
        private readonly bool $retryable = false,
        ?Throwable $previous = null,
        private readonly ?int $httpStatus = null,
        private readonly ?string $providerCode = null,
        ?string $validationCode = null,
    ) {
        $this->validationCode = is_string($validationCode)
            && preg_match('/\Aai_quality_[a-z_]{1,80}\z/D', $validationCode) === 1
                ? $validationCode
                : null;
        $safePrevious = $previous instanceof ArticleAiQualityCauseException
            ? $previous
            : ($previous ? new ArticleAiQualityCauseException($previous::class) : null);

        parent::__construct($safeCode, 0, $safePrevious);
    }

    public function safeCode(): string
    {
        return $this->safeCode;
    }

    public function retryable(): bool
    {
        return $this->retryable;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function providerCode(): ?string
    {
        return $this->providerCode;
    }

    public function validationCode(): ?string
    {
        return $this->validationCode;
    }
}
