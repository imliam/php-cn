<?php

namespace Cn\Support;

use Cn\ValueObjects\ClassPartObject;
use Cn\ValueObjects\ClassValidatorObject;
use Cn\ValueObjects\ThemeGetter;

class ClassMap
{
    final public const CLASS_PART_SEPARATOR = '-';

    /**
     * @param  array{prefix?: ?string, theme: array<array-key, mixed>, classGroups: array<array-key, mixed>}  $config
     */
    public static function create(array $config): ClassPartObject
    {
        $theme = $config['theme'];
        $prefix = $config['prefix'] ?? null;

        $classMap = new ClassPartObject();

        $prefixedClassGroupEntries = self::getPrefixedClassGroupEntries(
            $config['classGroups'],
            $prefix,
        );

        foreach ($prefixedClassGroupEntries as $classGroupId => $classGroup) {
            if (! is_array($classGroup)) {
                throw new \InvalidArgumentException('Class group definitions must be arrays.');
            }

            self::processClassesRecursively($classGroup, $classMap, (string) $classGroupId, $theme);
        }

        return $classMap;
    }

    /**
     * @param  array<array-key, mixed>  $classGroupEntries
     * @return array<array-key, mixed>
     */
    private static function getPrefixedClassGroupEntries(array $classGroupEntries, ?string $prefix): array
    {
        if ($prefix === null || ! str_ends_with($prefix, '-')) {
            return $classGroupEntries;
        }

        $prefixedEntries = [];

        foreach ($classGroupEntries as $classGroupId => $classGroup) {
            if (! is_array($classGroup)) {
                throw new \InvalidArgumentException('Class group definitions must be arrays.');
            }

            $prefixedGroup = [];

            foreach ($classGroup as $classDefinition) {
                if (is_string($classDefinition)) {
                    $prefixedGroup[] = $prefix . $classDefinition;
                } elseif (is_array($classDefinition) && ! is_callable($classDefinition)) {
                    foreach ($classDefinition as $key => $value) {
                        $prefixedGroup[] = [$prefix . $key => $value];
                    }
                } else {
                    $prefixedGroup[] = $classDefinition;
                }
            }

            $prefixedEntries[$classGroupId] = $prefixedGroup;
        }

        return $prefixedEntries;
    }

    /**
     * @param  array<array-key, mixed>  $classGroup
     * @param  array<array-key, mixed>  $theme
     */
    public static function processClassesRecursively(array $classGroup, ClassPartObject $classPartObject, string $classGroupId, array $theme): void
    {
        foreach ($classGroup as $classDefinition) {
            if (is_string($classDefinition)) {
                $classPartObjectToEdit = $classDefinition === '' ? $classPartObject : self::getPart($classPartObject, $classDefinition);
                $classPartObjectToEdit->classGroupId = $classGroupId;

                continue;
            }

            if ($classDefinition instanceof ThemeGetter) {
                self::processClassesRecursively(
                    $classDefinition->get($theme),
                    $classPartObject,
                    $classGroupId,
                    $theme,
                );

                continue;
            }

            if (is_callable($classDefinition)) {
                $classPartObject->validators[] = new ClassValidatorObject(
                    classGroupId: $classGroupId,
                    validator: $classDefinition(...),
                );

                continue;
            }

            if (! is_array($classDefinition)) {
                throw new \InvalidArgumentException('Class definitions must be strings, arrays, theme getters, or validators.');
            }

            foreach ($classDefinition as $key => $classGroup) {
                if (! is_array($classGroup)) {
                    throw new \InvalidArgumentException('Nested class group definitions must be arrays.');
                }

                self::processClassesRecursively(
                    $classGroup,
                    self::getPart($classPartObject, (string) $key),
                    $classGroupId,
                    $theme,
                );
            }
        }
    }

    private static function getPart(ClassPartObject $classPartObject, string $path): ClassPartObject
    {
        $currentClassPartObject = $classPartObject;

        foreach (explode(self::CLASS_PART_SEPARATOR, $path) as $pathPart) {
            $currentClassPartObject->nextPart[$pathPart] ??= new ClassPartObject();

            $currentClassPartObject = $currentClassPartObject->nextPart[$pathPart];
        }

        return $currentClassPartObject;
    }
}
