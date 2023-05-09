noresources/persistence
===========
Doctrine Persistence utilities.


## Installation

```bash
composer require noresources/persistence
```

## Features
* Basic implementation of ClassMetadata with compatibility with Doctrin ORM implementation
* ID generator interface and basic implementations
* Reflection & DocComment-based Mapping driver
  * Use DocComment to annotate entities
  * Accepts any ClassMetadata implementation
  * Compatible with most of Doctrine ORM features
* ClassMetadata factory implementation
  * Use mapping driver to load class metadata
  * Runtime cache
  * Persistent cache using PSR Cache interfaces
* ObjectManager generic implementation
* Object property <-> POD map mapping interface and Reflection-based implementation
* Object sorting interfaces
* PSR Cache utility interfaces

## References
* [Doctrine Persistence](https://github.com/doctrine/orm)
* [Doctrine ORM](https://github.com/doctrine/orm)
