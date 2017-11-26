[![Build Status](https://travis-ci.org/movephp/callback-container.svg?branch=master)](https://travis-ci.org/movephp/callback-container)
[![Coverage Status](https://coveralls.io/repos/github/movephp/callback-container/badge.svg?branch=master)](https://coveralls.io/github/movephp/callback-container?branch=master)

# Callback-контейнер

Библиотека представляет собой небольшой класс-обёртку над встроенным 
в PHP типом `callable`.

Главная особенность этого контейнера - он реализует интерфейс 
`Serializable`. Поскольку не всякий `callable` может быть сериализован,
контейнер предоставляет метод `isSerializable()` для удобной проверки
возможности сериализации.

## Оглавление

* [Пример использования](#Пример-использования)
* [Допустимые callback аргументы](#Допустимые-callback-аргументы)
* [Использование с PSR-контейнером](#Использование-с-psr-контейнером)
    * [Привязка PSR-контейнера после десериализации](#Привязка-psr-контейнера-после-десериализации)
* [Анализ параметров калбека](#Анализ-параметров-калбека)
* [Несериализуемые калбеки](#Несериализуемые-калбеки)
* [Практическое применение, пример](#Практическое-применение-пример)

## Пример использования

Рабочий объект контейнера создаётся через фабричный метод
`make()`:

    use \Movephp\CallbackContainer\Container;
    $factory = new Container();
    $callback = $factory->make('my_callback');
    
После чего его можно сериализовать:
    
    if($callback->isSerializable()){
        var_dump(serialize($callback));
    }
    
Или получить соответствующее замыкание:
    
    call_user_func($callback->closure());

> Попытка сериализовать контейнер с несериализуемым калбеком 
вызовет исключение 
`\Movephp\CallbackContainer\Exception\NonSerializableException`

> **Важно:** При сериализации CallbackContainer'а с калбеком на 
основе объекта `[$object, 'method']` сохраняется только имя 
класса объекта. С одной стороны, таким образом снижаются затраты
на процесс сериализации/десериализации и исключается возможные 
проблемы, связанные с тем, что сам переданный `$object` может
быть несериализуемым. С другой стороны, важно позаботиться
о том, чтобы после десериализации этот `$object` мог быть 
автоматически восстановлен по имени его класса (это возможно, если 
у него конструктор без аргументов или с использованием 
PSR-контейнера, см. далее).

## Допустимые callback аргументы

В таблице перечислены типы значений, которые может принимать 
метод `make()` в качестве аргумента:

Тип | Комментарий
--- | ---
Стандартный `callable` | Объект `Closure` (не может быть сериализован), строка с именем функции или массив вида `[$objectOrClassName, $methodName]`.
Устаревшая форма `callable`: `[$className, $nonStaticMethodName]` | Несмотря на то, что такая форма считается устаревшей, данный контейнер принимает её при условии, что объект указанного класса может быть создан без аргументов для конструктора или через PSR-контейнер.
Массив вида `[$psrContainerKey, $methodName]` | Допустуно при использовании данной библиотеки с PSR-контейнером (см. ниже).

## Использование с PSR-контейнером

Если в проекте используется DI-контейнер, реализующий 
интерфейс [PSR-11](http://www.php-fig.org/psr/psr-11/),
в качестве аргумента в метод `make()` можно передать массив,
похожий на обычный `callable`, в котором первым элемементом будет
ключ, по которому можно запросить нужный объект в PSR-контейнере.

В первую очередь необходимо привязать PSR-контейнер к данной библиотеке:

    use \Movephp\CallbackContainer\Container;
    $factory = new Container($psrContiner);
    $callback = $factory->make(['psr_container_key', 'method']);

Ключом для PSR-контейнера может быть, например, имя интерфейса, или
любая строка, для которой `$psrContiner->has($key)` вернёт `TRUE`.

### Привязка PSR-контейнера после десериализации

При сериализации CallbackContainer'а сохраняется только упрощённое
представление исходного калбека. Соответственно, если изначально
был использован PSR-контейнер, то после десериализации его нужно
будет повторно привязать к CallbackContainer'у.

Это можно сделать отдельно для каждого десеризованного 
CallbackContainer'а:

    $callback = unserialize($serialized);
    $callback->setPsrContainer($psrContiner);

Или можно задать PSR-контейнер глобально для всех будущих 
CallbackContainer'ов, которые будут созданы напрямую 
(`new Container()`) или при десериализации, вызвав предвариетльно
статический метод `setPsrContainerGlobal()`: 

    use \Movephp\CallbackContainer\Container;
    Container::setPsrContainerGlobal($psrContiner);
    $callback = unserialize($serialized);

## Анализ параметров калбека

Кроме прочего CallbackContainer имеет метод `parameters()`, который
возвращает массив объектов класса 
`Movephp\CallbackContainer\Parameter`, представляющих параметры,
принимаемые заданным калбеком.

Метод `parameters()` анализирует параметры через Reflection API
при первом обращении и запоминает результат при сериализации, 
таким образом увеличивая общую производительность, например, при
извлечении массива CallbackContainer'ов из кеша.

Класс `Movephp\CallbackContainer\Parameter` имеет несколько геттеров 
для получения подробной информации о параметре:

Метод | Возвращаемое значение
---|---
`name(): string` | Имя параметра 
`hasType(): bool` | `true`, если для параметра определён тип
`type(): string` | Строковое обозначение типа (`'int'`, `'string'` и т.д.)
`isVariadic(): bool` | `true`, если это параметр с переменным количеством аргументов
`isOptional(): bool` | `true`, если параметр является необязательным
`getDefault(): mixed` | Значение по-умолчанию для параметра, или `null`, если его нет

## Несериализуемые калбеки

Если вы хотите использовать возможность сериализации 
CallbackContainer'а, например, для кеширования с целью 
повысить производительность приложения, следует не только проверять
возможность сериализации каждого элемента через `isSerializable()`,
но также в целом иметь ввиду, какие виды калбеков не могут быть 
сериализованы. Это:

* Замыкания, т.е. объекты встроенного класса `\Closure`, которые, 
как правило, создаются путём объявления анонимных функций.
* Любые `callable`, связанные с анонимными классами, поскольку 
такие классы не имеют постоянного имени и их будет невозможно 
восстановить после десериализации.

## Практическое применение, пример

Допустим, существует некий механизм роутинга для приложения,
который связывает шаблоны маршрутов с методами классов, и который
достаточно сложно устроен, так что имеет смысл постараться 
закешировать его. Вот условный пример, как это может быть 
реализовано:

    use Movephp\CallbackContainer\Container;
    
    Container::setPsrContainerGlobal($psrContainer);
    
    if ($cache->isHit()) {
        $routes = $cache->get();
    } else {
        $callbackFactory = new Container();
    
        $rules = getRoutingRulesOverWholeProject(); // Собираем по всему проекту шаблоны роутов и связанные с ними калбеки
        $routes = [];
        foreach ($rules as $rule) {
            try {
                $callback = $callbackFactory->make($rule->callback);
            } catch (\Exception $e) {
                $logger->error($e);
                continue;
            }
            $routes[] = [
                'template' => $rule->template,
                'callback' => $callback
            ];
        }
    
        // Проверяем, что все калбеки сериализуемые
        $serializable = array_reduce(
            $routes,
            function ($result, $route) {
                return $result && $route['callback']->isSerializable();
            },
            true
        );
        if ($serializable) {
            // Кешируем результат
            $cache->set($routes);
            $cachePool->save($cache);
        }
    }