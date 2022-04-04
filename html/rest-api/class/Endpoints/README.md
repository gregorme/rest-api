# Custom Routes & Endpoints

- copy and rename the class file Example.php
- provide your namespace, name and description
- add your routes
- define all parameters for each route and method
  - undefined parameters will not be accessible in your callbacks!
- goto config.php and register your namespace
  - `router.namespaces = [...]`
  - unregistered namespaces cannot be requested or called

## Endpoint Settings
 
| Key         | Type            | Required | Default | Description                                                         |
|-------------|-----------------|----------|---------|---------------------------------------------------------------------|
| name        | string          | No       | route   | The name of your route                                              |
| description | string          | No       | ''      | A short explanation of the route, special features, tips etc.       |
| access      | string/callable | No       | public  | Access Level, role, capability or an validation callback.           |
| callback    | callable        | Yes      | null    | The callback function of the endpoint                               |
| parameters  | array           | Yes      | null    | List of all parameters ... query, GET, JSON body (first dimenstion) |

**The endpoint callback**
```php 
public function my_callback(Request $request): void {

  // on error
  new ErrorResponse($error_code, $error_message, $http_status, $data);
  // exceptions will be catched and send as ErrorResponse()
  throw new Exception(...);
  
  // on success
  $response = new Response($response_data);
  // additional settings
  $response->send();
}
```

## Parameter Settings

| Key         | Type   | Required | Default  | Description                                                                                    |
|-------------|--------|----------|----------|------------------------------------------------------------------------------------------------|
| type        | string | No       | string   | value type, one of [string, integer, number, float, bool, array, object]                       |
| required    | bool   | No       | false    | parameter must be set and not null or empty                                                    |
| default     | mixed  | No       | ''       | The default value of the parameter. For optional parameters only. Must match the defined type. |
| description | string | No       | ''       | A short description of the value, rules, format etc.                                           |
| minimum     | number | No       | null     | Minimum value (inclusive) for numberic types                                                   |
| maximum     | number | No       | null     | Maximum value (inclusive) for numeric types                                                    |
| enum        | array  | No       | null     | Enumeration- / White-List for string and numeric types                                         |
| validation  | array  | No       | defaults | Custom validation and format settings                                                          |


## Validation Settings
Unless otherwise defined, tasks `trim`, `type` and `cast` are always added and executed first. In this order.
All tasks are processed in that order in which they were defined. So order them to your needs.

| Key      | Type     | Required | Default | Description                                               |
|----------|----------|----------|---------|-----------------------------------------------------------|
| trim     | bool     | No       | true    | Strip whitespace from the beginning and end of a string   |
| type     | bool     | No       | true    | Checks whether the received value corresponds to the type |
| cast     | bool     | No       | true    | The value obtained is cast according to the type          |
| regex    | string   | No       | null    | Custom validation RegEx                                   |
| callback | callable | No       | null    | Custom validation callback function                       |
| valitron | array    | No       | null    | Valitron validation rules                                 |
| format   | callable | No       | null    | Custom formatting callback function                       |

**Callback functions**
```php
public function my_validation($value, $key, Request $request): bool {

    // validation
    return true or false
    
    // or create a custom error response
    new ErrorResponse($error_code, $error_message, $http_status, $data);
}

public function my_formatting($value, $key, Request $request){

    // your magic comes here
    
    return $value;
}
```

## Valitron Rules
@see https://github.com/vlucas/valitron  
The rules are checked in the defined order
```php
$rules = [
    'required',
    'integer',
    ['max', 100], // array syntax for rules with flags
];
```
## Built-in Validation Rules

* `required` - Field is required
* `requiredWith` - Field is required if any other fields are present
* `requiredWithout` - Field is required if any other fields are NOT present
* `equals` - Field must match another field (email/password confirmation)
* `different` - Field must be different than another field
* `accepted` - Checkbox or Radio must be accepted (yes, on, 1, true)
* `numeric` - Must be numeric
* `integer` - Must be integer number
* `boolean` - Must be boolean
* `array` - Must be array
* `length` - String must be certain length
* `lengthBetween` - String must be between given lengths
* `lengthMin` - String must be greater than given length
* `lengthMax` - String must be less than given length
* `min` - Minimum
* `max` - Maximum
* `listContains` - Performs in_array check on given array values (the other way round than `in`)
* `in` - Performs in_array check on given array values
* `notIn` - Negation of `in` rule (not in array of values)
* `ip` - Valid IP address
* `ipv4` - Valid IP v4 address
* `ipv6` - Valid IP v6 address
* `email` - Valid email address
* `emailDNS` - Valid email address with active DNS record
* `url` - Valid URL
* `urlActive` - Valid URL with active DNS record
* `alpha` - Alphabetic characters only
* `alphaNum` - Alphabetic and numeric characters only
* `ascii` - ASCII characters only
* `slug` - URL slug characters (a-z, 0-9, -, \_)
* `regex` - Field matches given regex pattern
* `date` - Field is a valid date
* `dateFormat` - Field is a valid date in the given format
* `dateBefore` - Field is a valid date and is before the given date
* `dateAfter` - Field is a valid date and is after the given date
* `contains` - Field is a string and contains the given string
* `subset` - Field is an array or a scalar and all elements are contained in the given array
* `containsUnique` - Field is an array and contains unique values
* `creditCard` - Field is a valid credit card number
* `instanceOf` - Field contains an instance of the given class
* `optional` - Value does not need to be included in data array. If it is however, it must pass validation.
* `arrayHasKeys` - Field is an array and contains all specified keys.

**NOTE**: If you are comparing floating-point numbers with min/max validators, you
should install the [BCMath](http://us3.php.net/manual/en/book.bc.php)
extension for greater accuracy and reliability. The extension is not required
for Valitron to work, but Valitron will use it if available, and it is highly
recommended.
