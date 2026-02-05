<?php

namespace Sendama\Engine\Util;

use Sendama\Engine\Exceptions\IOException;
use Swaggest\JsonSchema\Context;
use Swaggest\JsonSchema\Exception;
use Swaggest\JsonSchema\InvalidValue;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\SchemaContract;

/**
 * Class SchemaValidator
 *
 * A utility class for validating data against a schema.
 */
class SchemaValidator
{
    protected SchemaContract $schema;
    protected string|false $schemaFileContents;

    /**
     * SchemaValidator constructor.
     *
     * @param string $path The path to the schema file.
     *
     * @throws IOException If the schema file cannot be read.
     * @throws Exception
     * @throws InvalidValue
     */
    public function __construct(protected string $path)
    {
        $this->schemaFileContents = file_get_contents($path);

        if ($this->schemaFileContents === false) {
            throw new IOException("Could not read schema file at path: $path");
        }

        $this->schema = Schema::import($this->schemaFileContents);
    }

    /**
     * Validates the given data against the schema.
     *
     * @param mixed $data
     * @param Context|null $options
     * @return array|object|null
     * @throws InvalidValue
     */
    public function validate(mixed $data, ?Context $options = null): array|null|object
    {
        return $this->schema->in($data, $options);
    }
}