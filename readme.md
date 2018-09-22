# uob-parser-2

A JSON API for the University of Bedfordshire timetable system.

Try it out here: https://uob-timetable-api.adriankeenan.co.uk

Built using:

- [Slim](https://github.com/slimphp/Slim)
- [Guzzle](http://docs.guzzlephp.org/en/latest/)
- DOMDocument
- DOMXPath

Requirements:

- PHP 5.5
- Composer

## Preamble

This tool parses the HTML from the university timetable system available [here](http://timetable.beds.ac.uk/sws1819/programme.asp) when viewed in the `List` view.

Departments, courses and other options available on the timetable web page are provided by an auto generated [JavaScript file](http://timetable.beds.ac.uk/sws1819/js/data_autogen.js). This file is parsed by hand to obtain the list of departments and courses.

There are some sessions which have no module name when viewed in the `List` view (but strangely they are present in the `Grid` view). Sessions objects are returned with a boolean `is_valid` field to denote whether they are missing this information, which client applications can then use to determine whether to show a warning.

Some sessions which are available in many rooms are split in to different entries in the `List` view. The parser logic will combine these entries in a single session with all rooms listed.

## Usage

### Library

- Courses
    ```php
    $parser = new UoBParser\Parser();
    $courseData = $parser->getCourses();
    ```

    ```
    array(5) {
      ["api_version"]=>
      int(1)
      ["response_time"]=>
      float(0.80002999305725)
      ["error"]=>
      bool(false)
      ["courses"]=>
      array(3650) {
        [995]=>
        array(7) {
          ["id"]=>
          string(17) "BSCCS-S/02AA/1/FT"
          ["name"]=>
          string(51) "Computer Science - BSc (Hons) - Ltn - Year 1 Feb FT"
          ["level"]=>
          string(20) "Undergraduate Year 1"
          ["department"]=>
          array(3) {
            ["id"]=>
            string(5) "CM010"
            ["name"]=>
            string(41) "School of Computer Science and Technology"
            ["course_count"]=>
            int(581)
          }
          ["session_url"]=>
          string(100) "http://whatever/sessions?dept=CM010&course=BSCCS-S%2F02AA%2F1%2FFT&level=Undergraduate+Year+1"
          ["name_start"]=>
          string(16) "Computer Science"
          ["name_end"]=>
          string(32) "BSc (Hons) - Ltn - Year 1 Feb FT"
        }
      }
      ["departments"]=>
        [6]=>
        array(3) {
          ["id"]=>
          string(5) "CM010"
          ["name"]=>
          string(41) "School of Computer Science and Technology"
          ["course_count"]=>
          int(581)
        }
      }
    }
    ```

- Sessions
    ```php
    $parser = new UoBParser\Parser();
    $sessions = $parser->getSessions($deptartment_id, $course_id, $level);
    ```

    ```
    array(4) {
      ["api_version"]=>
      int(2)
      ["response_time"]=>
      float(1.1677050590515)
      ["error"]=>
      bool(false)
      ["sessions"]=>
      array(21) {
        [0]=>
        array(11) {
          ["module_name"]=>
          string(32) "Fundamentals Of Computer Studies"
          ["day"]=>
          int(0)
          ["start"]=>
          string(4) "9:00"
          ["end"]=>
          string(5) "11:00"
          ["length"]=>
          int(2)
          ["length_str"]=>
          string(7) "2 hours"
          ["type"]=>
          string(7) "Lecture"
          ["rooms"]=>
          array(1) {
            [0]=>
            string(36) "C016 - CST Teaching Lab"
          }
          ["rooms_short"]=>
          array(1) {
            [0]=>
            string(4) "C016"
          }
          ["hash"]=>
          string(32) "be39ce93b2a78f3b73b4e8cbe84559dc"
          ["is_valid"]=>
          bool(true)
        }
      }
    }
    ```

### Webservice

This project includes an HTTP webservice which returns data as JSON. To use, set `uob-parser-2/public` as the document root in your webserver.

Endpoints:
- Courses 
    ```GET /courses```

    ```
    {
        "api_version": 2,
        "response_time": 0.69508790969849,
        "error": false,
        "courses": [
            {
                "id": "BSCCS-S/02AA/1/CIS213/BSc (Hons)/FT",
                "name": "Computer Science - BSc (Hons) - Ltn - Year 1 Feb FT",
                "level": "Undergraduate Year 1",
                "department": {
                    "id": "12600",
                    "name": "Department of Computer Science and Technology",
                    "course_count": 581
                },
                "session_url": "http://whatever/sessions?dept=12600&course=BSCCS-S%2F02AA%2F1%2FCIS213%2FBSc+%28Hons%29%2FFT&level=Undergraduate+Year+1",
                "name_start": "Computer Science",
                "name_end": "BSc (Hons) - Ltn - Year 1 Fe - Ltn - Year 1 Feb FT"
            }
        ],
        "departments": [
            {
                "id": "12600",
                "name": "Department of Computer Science and Technology",
                "course_count": 581
            }
        ]
    }
    ```

- Sessions 
    ```GET /sessions?dept={department_id}&course={course_id}&level={level}```

    ```
    {
        "api_version": 2,
        "response_time": 1.2430939674377,
        "error": false,
        "sessions": [
            {
                "module_name": "Fundamentals Of Computer Studies",
                "day": 0,
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

Error responses will contain `error` set to true and an error reason in `error_str`, as well as a non-200 response code.

```
{
    "error": true,
    "error_str": "Invalid course details"
}
```

If the application is started in debug mode (`UOB_PARSER_DEBUG=1` in env), the exception details will also be included in the response:

```
{
    ...
    "exception": {
        "class": "InvalidArgumentException",
        "message": "Invalid course details",
        "code": 0,
        "file": "/.../uob-parser-2/src/UoBParser/Parser.php",
        "line": 128,
        "previous": null
    }
}
```