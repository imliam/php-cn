<?php

namespace Cn\Support;

use Cn\ValueObjects\ClassPartObject;
use Cn\ValueObjects\ParsedClass;

class TailwindClassParser
{
    final public const CLASS_PART_SEPARATOR = '-';

    final public const ARBITRARY_PROPERTY_REGEX = '/^\[(.+)\]$/';

    final public const IMPORTANT_MODIFIER = '!';

    private readonly ClassPartObject|CompiledClassMap $classMap;

    private readonly string $separator;

    private readonly ?string $prefix;

    /** @var array<string, true> */
    private readonly array $orderSensitiveModifiers;

    /** @var array<string, true> */
    private readonly array $postfixLookupClassGroups;

    /**
     * @param  array{theme: array<array-key, mixed>, classGroups: array<array-key, mixed>, prefix?: ?string, separator?: string, orderSensitiveModifiers?: list<string>, postfixLookupClassGroups?: list<string>}  $configuration
     */
    public function __construct(array $configuration, ?CompiledClassMap $compiledClassMap = null)
    {
        $this->classMap = $compiledClassMap ?? ClassMap::create($configuration);
        $this->separator = $configuration['separator'] ?? ':';

        if ($this->separator === '') {
            throw new \InvalidArgumentException('The modifier separator must not be empty.');
        }

        $prefix = $configuration['prefix'] ?? null;
        $this->prefix = $prefix !== null && $prefix !== '' && ! str_ends_with($prefix, '-')
            ? $prefix . $this->separator
            : null;
        $this->orderSensitiveModifiers = array_fill_keys($configuration['orderSensitiveModifiers'] ?? [], true);
        $this->postfixLookupClassGroups = array_fill_keys($configuration['postfixLookupClassGroups'] ?? [], true);
    }

    /**
     * Walk the trie with byte offsets, avoiding an array and a slice for every class part.
     */
    private static function getGroupRecursive(string $class, int $offset, ClassPartObject $classPartObject): ?string
    {
        $separatorPosition = strpos($class, self::CLASS_PART_SEPARATOR, $offset);
        $part = $separatorPosition === false ? substr($class, $offset) : substr($class, $offset, $separatorPosition - $offset);
        $nextPart = $classPartObject->nextPart[$part] ?? null;

        if ($nextPart !== null) {
            $group = $separatorPosition === false
                ? $nextPart->classGroupId
                : self::getGroupRecursive($class, $separatorPosition + 1, $nextPart);

            if ($group !== null) {
                return $group;
            }
        }

        if ($classPartObject->validators !== []) {
            $rest = $offset === 0 ? $class : substr($class, $offset);

            foreach ($classPartObject->validators as $validator) {
                if (($validator->validator)($rest)) {
                    return $validator->classGroupId;
                }
            }
        }

        return null;
    }

    public function parse(string $class): ParsedClass
    {
        $className = $class;

        if ($this->prefix !== null) {
            if (! str_starts_with($className, $this->prefix)) {
                return new ParsedClass([], false, false, '', null, $class, $class);
            }

            $className = substr($className, strlen($this->prefix));
        }

        [$modifiers, $baseClassName, $postfixPosition] = $this->splitModifiers($className);
        $hasImportantModifier = false;

        if (str_ends_with($baseClassName, self::IMPORTANT_MODIFIER)) {
            $baseClassName = substr($baseClassName, 0, -1);
            $hasImportantModifier = true;
        } elseif (str_starts_with($baseClassName, self::IMPORTANT_MODIFIER)) {
            $baseClassName = substr($baseClassName, 1);
            $hasImportantModifier = true;
            // The slash offset must track the stripped leading important marker.
            if ($postfixPosition !== null) {
                $postfixPosition--;
            }
        }

        $hasPostfixModifier = $postfixPosition !== null;
        $classGroupId = $this->getClassGroupId($hasPostfixModifier ? substr($baseClassName, 0, $postfixPosition) : $baseClassName);

        if ($hasPostfixModifier && ($classGroupId === null || isset($this->postfixLookupClassGroups[$classGroupId]))) {
            $fullClassGroupId = $this->getClassGroupId($baseClassName);

            if ($fullClassGroupId !== null && $fullClassGroupId !== $classGroupId) {
                $classGroupId = $fullClassGroupId;
                $hasPostfixModifier = false;
            }
        }

        $variantModifier = implode(':', $this->sortModifiers($modifiers));
        $modifierId = $hasImportantModifier ? $variantModifier . self::IMPORTANT_MODIFIER : $variantModifier;

        return new ParsedClass(
            modifiers: $modifiers,
            hasImportantModifier: $hasImportantModifier,
            hasPostfixModifier: $hasPostfixModifier,
            modifierId: $modifierId,
            classGroupId: $classGroupId,
            baseClassName: $baseClassName,
            originalClassName: $class,
        );
    }

    private function getClassGroupId(string $class): ?string
    {
        if (str_starts_with($class, '[') && str_ends_with($class, ']')) {
            $colon = strpos($class, ':');

            return $colon !== false && $colon > 1 ? 'arbitrary..' . substr($class, 1, $colon - 1) : null;
        }

        $offset = str_starts_with($class, '-') && $class !== '-' ? 1 : 0;

        if ($this->classMap instanceof CompiledClassMap) {
            return $this->classMap->getClassGroupId($offset === 0 ? $class : substr($class, 1));
        }

        return self::getGroupRecursive($class, $offset, $this->classMap);
    }

    /**
     * @return array{list<string>, string, ?int}
     */
    private function splitModifiers(string $className): array
    {
        // Most classes have no nested syntax or slash: let PHP split their variants in C.
        if (strpbrk($className, '[]()/') === false) {
            $parts = explode($this->separator, $className);
            $baseClassName = array_pop($parts);

            return [$parts, $baseClassName, null];
        }

        $modifiers = [];
        $bracketDepth = 0;
        $parenDepth = 0;
        $modifierStart = 0;
        $postfixPosition = null;
        $separatorLength = strlen($this->separator);
        $length = strlen($className);
        // Jump between significant characters in C instead of visiting every byte.
        $significant = $this->separator[0] . '/[]()';

        for ($index = strcspn($className, $significant); $index < $length; $index += 1 + strcspn($className, $significant, $index + 1)) {
            $character = $className[$index];

            if ($bracketDepth === 0 && $parenDepth === 0) {
                if ($character === $this->separator[0] && ($separatorLength === 1 || substr($className, $index, $separatorLength) === $this->separator)) {
                    $modifiers[] = substr($className, $modifierStart, $index - $modifierStart);
                    $modifierStart = $index + $separatorLength;
                    $index += $separatorLength - 1;

                    continue;
                }

                if ($character === '/') {
                    $postfixPosition = $index;

                    continue;
                }
            }

            if ($character === '[') {
                $bracketDepth++;
            } elseif ($character === ']') {
                $bracketDepth--;
            } elseif ($character === '(') {
                $parenDepth++;
            } elseif ($character === ')') {
                $parenDepth--;
            }
        }

        return [
            $modifiers,
            substr($className, $modifierStart),
            $postfixPosition !== null && $postfixPosition > $modifierStart ? $postfixPosition - $modifierStart : null,
        ];
    }

    /**
     * @param  list<string>  $modifiers
     * @return list<string>
     */
    private function sortModifiers(array $modifiers): array
    {
        if (count($modifiers) <= 1) {
            return $modifiers;
        }

        $sorted = [];
        $segment = [];

        foreach ($modifiers as $modifier) {
            if (str_starts_with($modifier, '[') || isset($this->orderSensitiveModifiers[$modifier])) {
                sort($segment, SORT_STRING);
                array_push($sorted, ...$segment);
                $sorted[] = $modifier;
                $segment = [];
            } else {
                $segment[] = $modifier;
            }
        }

        sort($segment, SORT_STRING);
        array_push($sorted, ...$segment);

        return $sorted;
    }
}
