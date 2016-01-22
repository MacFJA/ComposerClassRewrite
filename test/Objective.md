```php
class A
{
    public function who()
    {
        return 'A';
    }
}

class B extends A
{
    public function who()
    {
        return parent::who() . '-B';
    }
}

class C extends A implements MacFJA\ClassRewrite\Rewriter
{
    public function who()
    {
        return parent::who() . '-C';
    }
}

$b = new B();
echo $b->who(); // Output: "A-C-B"
```

Principle:

 - Read all classes
 - Found all classes that implements `MacFJA\ClassRewrite\Rewriter`
 - Store that `C` rewrite `A`
 - When `B` request for `A` (on autoloading process) return `C`
 - When `C` request for `A` return `A`

Ideas:

 - When a class is rewrited (`A`), change its name to something else (hard to get) and rename the rewriter (`C`) into the name of its rewrite (`A`)