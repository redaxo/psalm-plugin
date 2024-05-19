<?php

declare(strict_types=1);

namespace Redaxo\PsalmPlugin\Provider;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Scalar\String_;
use Psalm\Plugin\EventHandler\Event\FunctionReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\FunctionReturnTypeProviderInterface;
use Psalm\Plugin\EventHandler\MethodReturnTypeProviderInterface;
use Psalm\Type;
use Psalm\Type\Atomic\TArray;
use Psalm\Type\Atomic\TArrayKey;
use Psalm\Type\Atomic\TKeyedArray;
use Psalm\Type\Atomic\TMixed;
use Psalm\Type\Atomic\TNull;
use Psalm\Type\Atomic\TString;
use Psalm\Type\Union;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Util\Type as RedaxoType;
use rex_request;
use rex_type;

use function assert;
use function in_array;

/**
 * @internal
 */
final class RexTypeReturnProvider implements MethodReturnTypeProviderInterface, FunctionReturnTypeProviderInterface
{
    public static function getClassLikeNames(): array
    {
        return [rex_type::class, rex_request::class, RedaxoType::class, Request::class];
    }

    public static function getFunctionIds(): array
    {
        return ['rex_get', 'rex_post', 'rex_request', 'rex_server', 'rex_session', 'rex_cookie', 'rex_files', 'rex_env'];
    }

    public static function getMethodReturnType(MethodReturnTypeProviderEvent $event): ?Union
    {
        if (in_array($event->getFqClasslikeName(), [rex_type::class, RedaxoType::class], true)) {
            if ('cast' === $event->getMethodNameLowercase()) {
                return self::resolveType($event->getCallArgs()[1]->value);
            }

            return null;
        }

        switch ($event->getMethodNameLowercase()) {
            case 'get':
            case 'post':
            case 'request':
            case 'server':
            case 'session':
            case 'cookie':
            case 'files':
            case 'env':
                $callArgs = $event->getCallArgs();
                if (!isset($callArgs[1])) {
                    return null;
                }
                $argType = $callArgs[1];
                $argDefault = $callArgs[2] ?? null;
                break;
            case 'arraykeycast':
                $callArgs = $event->getCallArgs();
                $argType = $callArgs[2];
                $argDefault = $callArgs[3] ?? null;
                break;
            default:
                return null;
        }

        return self::resolveType($argType->value, $argDefault->value ?? null);
    }

    public static function getFunctionReturnType(FunctionReturnTypeProviderEvent $event): ?Union
    {
        $callArgs = $event->getCallArgs();

        if (!isset($callArgs[1])) {
            return null;
        }

        return self::resolveType($callArgs[1]->value, $callArgs[2]->value ?? null);
    }

    private static function resolveType(Expr $typeExpr, ?Expr $defaultExpr = null): Union
    {
        if ($typeExpr instanceof String_) {
            $type = self::resolveTypeFromString($typeExpr);
        } elseif ($typeExpr instanceof Array_) {
            $type = self::resolveTypeFromArray($typeExpr);
        } else {
            return Type::getMixed();
        }

        if ($defaultExpr instanceof ConstFetch && 'null' === $defaultExpr->name->getFirst()) {
            return $type->getBuilder()->addType(new TNull())->freeze();
        }

        return $type;
    }

    private static function resolveTypeFromString(String_ $string): Union
    {
        $vartype = $string->value;

        if (in_array($vartype, [
            'bool',
            'boolean',
            'int',
            'integer',
            'double',
            'float',
            'real',
            'string',
            'object',
            'array',
        ], true)) {
            return Type::parseString($vartype);
        }

        if (preg_match('/^array\[(.+)\]$/', $vartype, $match)) {
            return new Union([new TArray([
                new Union([new TArrayKey()]),
                Type::parseString($match[1]),
            ])]);
        }

        return Type::getMixed();
    }

    private static function resolveTypeFromArray(Array_ $array): Union
    {
        $fallback = new Union([new TArray([
            new Union([new TString()]),
            new Union([new TMixed()]),
        ])]);

        $types = [];

        foreach ($array->items as $item) {
            assert(null !== $item);

            if (!$item->value instanceof Array_) {
                return $fallback;
            }

            $subItems = $item->value->items;

            if (!isset($subItems[0])) {
                return $fallback;
            }

            $itemKey = $subItems[0]->value;

            if (!$itemKey instanceof String_) {
                return $fallback;
            }

            $key = $itemKey->value;

            if (!isset($subItems[1])) {
                $type = Type::getMixed();
            } else {
                $type = self::resolveType($subItems[1]->value, $subItems[2]->value ?? null);
            }

            $types[$key] = $type;
        }

        if (!$types) {
            return $fallback;
        }

        return new Union([new TKeyedArray($types)]);
    }
}
