# CODING STANDARDS

Zend Framework projects (except for Zend Framework 1) follow
[PSR-1](http://www.php-fig.org/psr/psr-1/) and [PSR-2](http://www.php-fig.org/psr-2/) coding
standards. 

Zend Framework 1 standards are defined in the [Zend Framework
manual](http://framework.zend.com/manual/1.12/en/coding-standard.html). The rest of this document
covers rules specific to Zend Framework 2 and its components that are not covered in PSR-1/2.

## Conventions

The rules below follow [RFC 2119](http://www.ietf.org/rfc/rfc2119.txt)'s verbiage for indicating
requirements.

- MUST and MUST NOT indicate non-optional requirements
- SHOULD and SHOULD NOT indicate recommendations for which exceptions may exist
- MAY indicates truly optional requirements

## General

Inclusion of arbitrary binary data as permitted by `__HALT_COMPILER()` MUST NOT be used in PHP files
in the Zend Framework project or files derived from them. Use of this feature is only permitted for
some installation scripts.

## Maximum Line Length

The maximum length of any line of PHP code is 120 characters.

## Line Termination

Line termination follows the Unix text file convention. Lines MUST end with a single linefeed (`LF`)
character. Linefeed characters are represented as ordinal 10, or hexadecimal `0x0A`.

Do not use carriage returns (`CR`) as is the convention in Apple OS's (`0x0D`) or the carriage
return - linefeed combination (`CRLF`) as is standard for the Windows OS (`0x0D`, `0x0A`).  There is
one exception to this rule: the last line of a file MUST NOT end in a linefeed.

Finally, lines MUST NOT have whitespace characters preceding the linefeed character. This is to
prevent versioning differences that affect only white characters at line endings.

## Abstract Classes

Abstract classes follow the same conventions as classes, with one additional rule: abstract class
names SHOULD begin with the term, `Abstract`. As examples, `AbstractAdapter` and `AbstractWriter`
are both considered valid abstract class names.

Abstract classes SHOULD be in the same namespace as concrete implementations. The following would be
considered invalid usage:

```php
namespace Zend\Log; 

abstract class AbstractWriter implements Writer 
{ 
} 

namespace Zend\Log\Writer; 

use Zend\Log\AbstractWriter; 

class StreamWriter extends AbstractWriter 
{ 
} 
```

While the next example displays proper usage:

```
namespace Zend\Log\Writer; 

use Zend\Log\Writer; 

abstract class AbstractWriter implements Writer 
{ 
} 

class StreamWriter extends AbstractWriter 
{ 
} 
```

The `Abstract` prefix MAY be omitted from the class name in the case of abstract classes that
implement only static functionality, such as factories. As an example, the following is valid usage:

```php
namespace Zend\Uri; 

abstract class UriFactory 
{ 
    public static function factory($uri) 
    { 
        // work goes in here... 
    } 
} 
```

## Interfaces

Interfaces follow the same conventions as classes, with two additional rules: interface names MUST
be nouns or adjectives and interface class names SHOULD end with the term, `Interface`. As examples,
`ServiceLocationInterface`, `EventCollectionInterface`, and `PluginLocatorInterface` are all
considered appropriate interface names.

Interfaces MUST be in the same namespace as concrete implementations.

```
namespace Zend\Log\Writer; 

interface class WriterInterface 
{ 
} 
```

## Variables

Variable names MUST contain only alphanumeric characters. Underscores are not permitted. Numbers are
permitted in variable names but are discouraged in most cases.

For variables that are declared with private or protected visibility, the first character of the
variable name MAY be a single underscore. This is the only acceptable application of an underscore
in a variable name, and is discouraged (as it makes refactoring to public visibility more
difficult). Member variables declared with public visibility SHOULD NOT start with an underscore.

Variable names MUST always start with a lowercase letter and follow the `$camelCase` capitalization
convention.

Verbosity is encouraged. Variables should always be as verbose as practical to describe the data
that the developer intends to store in them. Terse variable names such as `$i` and `$n` are
discouraged for all but the smallest loop contexts. If a loop contains more than 20 lines of code,
the index variables should have more descriptive names.

## String Literals

When a string is literal (contains no variable substitutions), the apostrophe or "single quote"
SHOULD be used to demarcate the string:

```php
$a = 'Example String'; 
```

## String Literals Containing Apostrophes

When a literal string itself contains apostrophes, you MAY demarcate the string with quotation marks
or "double quotes".

```php
$string = "That's what I always said!";
```

This syntax is preferred over escaping apostrophes as it is much easier to read.

## Variable Substitution

Variable substitution SHOULD use either of the following forms:

```php
$greeting = "Hello $name, welcome back!"; 

$greeting = "Hello {$name}, welcome back!"; 

$greeting = sprintf('Hello %s, welcome back!', $name);
```

For consistency, this form SHOULD NOT be used:

```php
$greeting = "Hello ${name}, welcome back!";
```

## Class Names in Strings

When evaluating a class name provided as a string, PHP always considers it globally qualified. As
such, provide the fully qualified class name, without a leading namespace separator (`\\`). As an
example, NEVER use:

```php
$class = '\Zend\Log\Writer\StreamWriter';
```

but instead use:

```php
$class = 'Zend\Log\Writer\StreamWriter';
```

## Numerically Indexed Arrays

When declaring indexed arrays with the array function, a trailing space MUST be added after each
comma delimiter to improve readability:

```php
$sample = [1, 2, 3, 'Zend', 'Framework']; 
```

It is permitted to declare multi-line indexed arrays using the array construct. In this case, each
element MUST be on its own line, at one additional level of indentation from the parent line, and
all successive lines MUST have the same indentation. The closing array character MUST be on a line
by itself at the same indentation level as the line opening the array declaration. The final element
in the array MUST include a trailing comma; this minimize the impact of adding new items on
successive lines, reducing parse errors caused by a missing comma delimiter.

```php
$sample = [
    1,
    2,
    3,
    'Zend',
    'Framework',
    $a,
    $b,
    $c,
    56.44,
    $d,
    500,
]; 
```

## Associative Arrays

Associative arrays follow the same general rules as Numerically Indexed Arrays. Additionally, the
`=>` assignment operators SHOULD be padded with white space such that both the keys and the values
for each item are aligned:

```php
$array = [
    'firstKey'  => 'firstValue', 
    'secondKey' => 'secondValue',
]; 
```

## Import Statements

All explicit dependencies used by a class MUST be imported. These include
classes and interfaces used in method typehints and explicit `instanceof` type
checks, classes directly instantiated, etc. Exceptions include:

- Code within the current namespace or subnamespaces of the current namespace.
- Class names that are dynamically resolved (e.g., from a plugin broker).

There MUST be one use keyword per declaration.

There MUST be one blank line after the use block.

Import statements SHOULD be in alphabetical order, to make scanning for entries predictable.

## Class Member Variables

Member variables MUST be named according to Zend Framework's variable naming conventions.

Any variables declared in a class MUST be listed at the top of the class, above the declaration of
any methods.

The `var` construct MUST NOT be used. Member variables MUST declare their visibility by using one of
the `private`, `protected`, or `public` visibility modifiers. Giving access to member variables directly
by declaring them as public MAY be done, but is discouraged.

## Exceptions

Each component SHOULD have a marker `ExceptionInterface` interface.

Concrete exceptions for a component MUST live in an "Exception" subnamespace. Concrete exception
classes MUST implement the component's `ExceptionInterface`, and SHOULD extend one of PHP's SPL
exception types. Concrete exception classes SHOULD be named after the SPL exception type they
extend, but MAY have a more descriptive name if warranted.

Subcomponents MAY define their own exception marker interface and concrete implementations,
following the same rules as at the root component level. The subcomponent `ExceptionInterface`
SHOULD extend the parent component's `ExceptionInterface`.

As an example:

```php
/** 
 * Component exceptions 
 */ 

// In Zend/Foo/Exception/ExceptionInterface.php:
namespace Zend\Foo\Exception; 

interface ExceptionInterface 
{ 
} 

// In Zend/Foo/Exception/RuntimeException.php:
namespace Zend\Foo\Exception; 

// Note: RuntimeException is not imported, as the name would conflict
// with the class being declared.
class RuntimeException extends \RuntimeException implements ExceptionInterface 
{ 
} 

// In Zend/Foo/Exception/TripwireException.php:
namespace Zend\Foo\Exception; 

class TripwireException extends RuntimeException implements ExceptionInterface 
{ 
} 

/** 
 * Subcomponent exceptions 
 */ 

// In Zend/Foo/Bar/Exception/ExceptionInterface.php:
namespace Zend\Foo\Bar\Exception; 

use Zend\Foo\Exception\ExceptionInterface as FooExceptionInterface; 

interface ExceptionInterface extends FooExceptionInterface 
{ 
} 

/** 
 * Subcomponent concrete exception
 */ 

// In Zend/Foo/Bar/Exception/DomainException.php:
namespace Zend\Foo\Bar\Exception; 

// Note: DomainException is not imported, as the name would conflict
// with the class being declared.
class DomainException extends \DomainException implements ExceptionInterface
{ 
} 
```

## Using Exceptions

Concrete exceptions SHOULD NOT be imported; instead, either use exception classes within your
namespace, or import the `Exception` namespace you will use.

```php
namespace Zend\Foo; 

class Bar 
{ 
    public function trigger() 
    { 
        // Resolves to Zend\Foo\Exception\RuntimeException 
        throw new Exception\RuntimeException(); 
    } 
} 

// Explicit importing: 

namespace Zend\Foo\Bar; 

use Zend\Foo\Exception; 

class Baz 
{ 
    public function trigger() 
    { 
        // Resolves to Zend\Foo\Exception\RuntimeException 
        throw new Exception\RuntimeException(); 
    } 
} 
```

When throwing an exception, you SHOULD provide a useful exception message. Such messages SHOULD
indicate the root cause of an issue, and provide meaningful diagnostics. As an example, you may want
to include the following information:

- The method throwing the exception (`__METHOD__`)
- Any parameters that were involved in calculations that led to the exception (often class names or
  variable types will be sufficient)

We recommend using `sprintf()` to format your exception messages.

```php
throw new Exception\InvalidArgumentException(sprintf( 
    '%s expects a string argument; received "%s"', 
    __METHOD__, 
    (is_object($param) ? get_class($param) : gettype($param)) 
));
```

## Inline Documentation

Classes and interfaces referenced by annotations MUST follow the same resolution order as PHP. In
other words:

- If the class is in the same namespace, simply refer to the class name without the namespace:
  ```php
  namespace Foo\Component; 
  
  class Bar 
  { 
      /** 
       * Assumes Foo\Component\Baz: 
       * @param Baz $baz 
       */ 
      public function doSomething(Baz $baz) 
      { 
      } 
  } 
  ```

- If the class is in a subnamespace of the current namespace, refer to it relative to the current namespace:
  ```php
  namespace Foo\Component; 
  
  class Bar 
  { 
      /** 
       * Assumes Foo\Component\Adapter\Baz: 
       * @param Adapter\Baz $baz 
       */ 
      public function doSomething(Adapter\Baz $baz) 
      { 
      } 
  } 
  ```

- If the class is imported, either via a namespace or explicit class name, use the name as specified
  by the import:
  ```php
  namespace Foo\Component; 
  
  use Zend\EventManager\EventManager as Events, 
  Zend\Log; 
  
  class Bar 
  { 
      /** 
       * Assumes Zend\EventManager\EventManager and Zend\Log\Logger: 
       * @param Events $events 
       * @param Log\Logger $log 
       */ 
      public function doSomething(Events $events, Log\Logger $log) 
      { 
      } 
  } 
  ```

- If the class is from another namespace, but not explicitly imported, provide a globally resolvable
  name:
  ```php
  namespace Foo\Component; 
  
  class Bar 
  { 
      /** 
       * Assumes \Zend\EventManager\EventManager: 
       * @param \Zend\EventManager\EventManager $events 
       */ 
      public function doSomething(\Zend\EventManager\EventManager $events) 
      { 
      } 
  } 
  ```

> This last case should rarely happen, primarily since you should be importing any dependencies. One
> case where it may happen, however, is in `@return` annotations, as return types might be determined
> outside the class scope.

