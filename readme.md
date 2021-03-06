# uob-parser-2

__⚠️ This project is no longer maintained and likely requires changes to return the correct data for 2019/2020 and onwards.__

[![Build Status](https://travis-ci.com/adriankeenan/uob-parser-2.svg?branch=master)](https://travis-ci.com/adriankeenan/uob-parser-2)

A parser for the University of Bedfordshire timetable system which includes a JSON REST
API for accessing the data.

Try it out here: https://uob-timetable-api.adriankeenan.co.uk

Built using:

- [Slim](https://github.com/slimphp/Slim)
- [Guzzle](http://docs.guzzlephp.org/en/latest/)
- [Carbon](https://carbon.nesbot.com)
- [DOMDocument](http://php.net/manual/en/class.domdocument.php)
- [DOMXPath](http://php.net/manual/en/class.domxpath.php)

Requirements:

- PHP >= 7.1
- Composer
- PHP AST extension (dev only)

## Preamble

This tool parses the HTML from the university timetable system available [here](http://timetable.beds.ac.uk/sws1819/programme.asp) when viewed in the `List` view.

Departments, courses and other options available on the timetable web page are provided by an auto generated [JavaScript file](http://timetable.beds.ac.uk/sws1819/js/data_autogen.js). This file is parsed by hand to obtain the list of departments and courses.

Some sessions which are available in many rooms are split in to different entries in the `List` view. The parser logic will combine these entries in a single session with all rooms listed.

## Limitations

All session and course data fields provided on the official university website are included in this library, but there are some quirks with the data:

- __Some sessions are missing the module name__
    
    This is due to the module name column being empty in `List` view, which this data is parsed from. Strangely this does not occur in `Grid` view but this is too complex to parse. Sessions objects are returned with a boolean `is_valid` field to denote whether they are missing this information, which client applications can then use to determine whether to show a warning.
- __Some courses have an incorrect level set__
    
    Some courses have a different level in the `name` field to the actual `level` field
    ```javascript
    {
        // ...
        "name": "EFL Intermediate - UG Credit - Ltn - Year 1 Oct SFT",
        "level": "Undergraduate Year 4",
    }
    ```

    The university website allows filtering courses by the `level` field, therefore it is affected by his inconsistency.


## Testing

- Run the [PHPUnit](https://github.com/sebastianbergmann/phpunit) test suite with `composer test`
- Run static analysis using [Phan](https://github.com/phan/phan) with `composer phan`
- Check code syntax compatibility with PHP >= 7.1 using [PHPCompatibility
](https://github.com/PHPCompatibility/PHPCompatibility) `composer php-compatibility`

## Usage

### Library

- Courses
    ```php
    $parser = new UoBParser\Parser();
    $courseData = $parser->getCourses();
    ```

    ```php
    object(UoBParser\Responses\CoursesResponse)#3615 (3) {
      ["courses"]=>
      array(3520) {
        [3126]=>
        object(UoBParser\Entities\Course)#1679 (5) {
          ["id"]=>
          string(17) "BSCES-S/10AA/1/FT"
          ["name"]=>
          string(55) "Computer Science - BSc (Hons) - Ltn - Year 1 Feb FT"
          ["level"]=>
          string(20) "Undergraduate Year 1"
          ["departmentId"]=>
          string(5) "CM010"
          ["department"]=>
          object(UoBParser\Entities\Department)#77 (3) {
            ["id"]=>
            string(5) "CM010"
            ["name"]=>
            string(41) "School of Computer Science and Technology"
            ["courseCount"]=>
            int(581)
          }
        }
      }
      ["departments"]=>
      array(17) {
        [6]=>
        object(UoBParser\Entities\Department)#77 (3) {
          ["id"]=>
          string(5) "CM010"
          ["name"]=>
          string(41) "School of Computer Science and Technology"
          ["courseCount"]=>
          int(568)
        }
      }
      ["levels"]=>
      array(8) {
        [4]=>
        object(UoBParser\Entities\Level)#3609 (1) {
          ["name"]=>
          string(20) "Undergraduate Year 1"
        }
      }
    }
    ```

- Sessions
    ```php
    $parser = new UoBParser\Parser();
    $sessions = $parser->getSessions($deptartmentId, $courseId, $level);
    ```

    ```php
    object(UoBParser\Responses\SessionsResponse)#106 (2) {
      ["timetableUrl"]=>
      string(36) "https://timetable.beds.ac.uk/sws1819"
      ["courseName"]=>
      string(51) "Computer Science - BSc (Hons) - Ltn - Year 1 Feb FT"
      ["dateRange"]=>
      string(44) "Weeks: 24-31, 33-36 (10 Jun 2019-8 Sep 2019)"
      ["sessions"]=>
      array(21) {
        [0]=>
        object(UoBParser\Entities\Session)#85 (6) {
          ["moduleName"]=>
          string(25) "Fundamentals Of Computer Studies"
          ["type"]=>
          string(7) "Lecture"
          ["day"]=>
          int(0)
          ["start"]=>
          string(5) "9:00"
          ["end"]=>
          string(5) "11:00"
          ["rooms"]=>
          array(1) {
            [0]=>
            string(36) "C016 - CST Teaching Lab"
          }
        }
      }
    }
    ```

#### Errors

Exceptions will be thrown either as `\Exception` or [`\UoBParser\Error`](src/Error.php), which includes an additional `getID()` method. The list of potention error IDs can be found [here](src/Parser.php).

### Webservice

This project includes an HTTP webservice which returns data as JSON. To use, set `uob-parser-2/public` as the document root in your webserver.

Endpoints:
- Courses 
    ```GET /courses```

    ```javascript
    {
        "api_version": 2,
        "response_time": 0.7,
        "error": false,
        "courses": [
            {
                "id": "BSCCS-S/02AA/1/CIS213/BSc (Hons)/FT",
                "name": "Computer Science - BSc (Hons) - Ltn - Year 1 Feb FT",
                "name_start": "Computer Science",
                "name_end": "BSc (Hons) - Ltn - Year 1 Fe - Ltn - Year 1 Feb FT",
                "level": "Undergraduate Year 1",
                "department": {
                    "id": "12600",
                    "name": "Department of Computer Science and Technology",
                    "course_count": 581
                },
                "session_url": "https://example.com/sessions?dept=12600&course=BSCCS-S%2F02AA%2F1%2FCIS213%2FBSc+%28Hons%29%2FFT&level=Undergraduate+Year+1"
            }
        ],
        "departments": [
            {
                "id": "12600",
                "name": "Department of Computer Science and Technology",
                "course_count": 581
            }
        ],
        "levels": [
            {
                "name": "Undergraduate Year 1"
            }
        ]
    }
    ```

- Sessions 
    ```GET /sessions?dept={department_id}&course={course_id}&level={level}```

    ```javascript
    {
        "api_version": 2,
        "response_time": 1.24,
        "error": false,
        "timetable_url": "https://timetable.beds.ac.uk/sws1819",
        "course_name": "Computer Science - BSc (Hons) - Ltn - Year 1 Feb FT",
        "date_range": "Weeks: 24-31, 33-36 (10 Jun 2019-8 Sep 2019)",
        "sessions": [
            {
                "module_name": "Fundamentals Of Computer Studies",
                "day": 0,
                "day_name": "Monday",
                "start": "9:00",
                "end": "11:00",
                "length": 2,
                "length_str": "2 hours",
                "type": "Lecture",
                "rooms": [
                    "C016 - CST Teaching Lab"
                ],
                "rooms_short": [
                    "C016"
                ],
                "hash": "be39ce93b2a78f3b73b4e8cbe84559dc",
                "is_valid": true
            }
        ]
    }
    ```

#### Versioning

Clients can specify a specific version number for the API when making requests in order to ensure that the response format will always be the same.

By default, version `1` will be used. The current version is `2`.

Specify a specific version by:
- Adding an `API-Version` header, eg `API-Version: 2`
- Adding a `api_version` query parameter, eg `api_version=2`

Sucessful requests will contain the requested version in the response `api_version` field and `API-Version` response header.

#### Errors

Error responses will contain
- `error` set to true
- `error_str` - Human readible error string
- `error_id` - ID for expected exceptions (values defined [here](src/UoBParser/Parser.php))

```javascript
{
    "error": true,
    "error_str": "Invalid course details",
    "error_id": "course_invalid"
}
```

If the application is started in debug mode (`UOB_PARSER_DEBUG=1` in env), the exception details will also be included in the response:

```javascript
{
    // ...
    "exception": {
        "class": "UoBParser\Error",
        "message": "Invalid course details",
        "id": "course_invalid",
        "code": 0,
        "file": "/.../uob-parser-2/src/UoBParser/Parser.php",
        "line": 128,
        "previous": null
    }
}
```

## License

[MIT](LICENSE)
