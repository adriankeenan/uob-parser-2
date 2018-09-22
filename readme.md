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

This tool parses the output of the _List_ timetable layout available [here](http://timetable.beds.ac.uk/sws1617/programme.asp). The timetable system itself is provided by _Scientia Ltd_, as it is a third party system the output is unlikely to change.

Departments, courses and other options available on the timetable web page are provided by http://timetable.beds.ac.uk/sws1617/js/data_autogen.js. We parse this file to attain the list of departments and courses.

While the output format is fairly reliable, there are some sessions which have no module code and title. This anomoly does not occur in the _Grid_ view, but this is virtually impossible to parse. Sessions objects are returned with a boolean ```is_valid``` field to denote whether they are missing this information.

## Usage

When used as a library the parser will return an array:

- Courses
	```php
	$parser = new UoBParser\Parser();
	$courseData = $parser->getCourses();
	```
	
- Sessions
	```php
	$parser = new UoBParser\Parser();
	$sessions = $parser->getSessions($dept, $course, $level);
	```



When used as a web service the parser will return a JSON response:

- Courses
	```
	/courses
	```

- Sessions
	```
	/sessions?dept=_&course=_&level=_
	```

## Testing

The webservice can be tested using the built in php webserver. Use the following command to start the server:

```UOB_PARSER_DEBUG=1 php -S localhost:9000 -t public```

Then navigate to [http://localhost:9000/courses](http://localhost:9000/courses)

## Output

### Courses

```
{
    "response_time": 0.69508790969849,
    "error": false,
    "courses": 
	[
		{
		    "id": "BSCCS-S/02AA/1/CIS213/BSc (Hons)/FT",
		    "name": "Computer Science - BSc (Hons) - Ltn - Year 1 Feb FT",
		    "level": "Undergraduate Year 1",
		    "department": 
		    {
		        "id": "12600",
		        "name": "Department of Computer Science and Technology",
		        "course_count" : 125
		    },
		    "session_url": "http://whatever/sessions?dept=12600&course=BSCCS-S%2F02AA%2F1%2FCIS213%2FBSc+%28Hons%29%2FFT&level=Undergraduate+Year+1",
		    "name_start": "Computer Science",
		    "name_end": "BSc (Hons) - Ltn - Year 1 Fe - Ltn - Year 1 Feb FT"
		}
	],
	"departments" : 
	[
		{
		    "id": "12600",
		    "name": "Department of Computer Science and Technology",
		    "course_count" : 125
		}
	]
}
```

### Session

```
{
    "response_time": 1.2430939674377,
    "error": false,
    "sessions": 
	[
		{
		    "module_code": "", // Always empty
		    "module_name": "Fundamentals Of Computer Studies",
		    "day": 0,
		    "start": "9:00",
		    "end": "11:00",
		    "length": 2,
		    "length_str": "2 hours",
		    "type": "Lecture",
		    "rooms": 
			[
			    "C016 - CST Teaching Lab"
			],
			"rooms_short": 
			[
			    "C016"
			],
			"staff": [], // Always empty
			"hash": "be39ce93b2a78f3b73b4e8cbe84559dc",
			"is_valid": true
		}
	]
}
```
