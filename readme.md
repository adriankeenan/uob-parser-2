# uob-parser-2

A JSON API for the University of Bedfordshire timetable system.

This is a replacement for the v1 parser.

Built using:

- Klein
- DOMDocument
- DOMXPath

Requirements:

- PHP 5.4
- Composer

## Background

This tool parses the output of the _List_ timetable layout available [here](http://timetable.beds.ac.uk/sws1415/programme.asp). The timetable system itself is provided by _Scientia Ltd_, as it is a third party system the output is unlikely to change.

Departments, courses and other options available on the timetable web page are provided by http://timetable.beds.ac.uk/sws1415/js/data_autogen.js. We parse this file to attain the list of departments and courses.

## Usage

When used as a library the parser will return an array:

- Courses
	```php
	$parser = new UoBParser\Parser();
	$courseData = $parser->getCourses());
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


## Limitations

While the output format is fairly reliable, there are some sessions which have no module code and title. This anomoly does not occur in the _Grid_ view, but this is virtually impossible to parse. Sessions objects are returned with a boolean ```is_valid``` field to denote whether they are missing this information.

Also this newer version of the parser does not support parsing term dates. That was a nice feature but the source page appeared to be edited by hand and humans aren't very consistent so it was a huge pain to maintain support for it.

## Example output

### Session

```
{
    "response_time": 1.2430939674377,
    "error": false,
    "sessions": 
	[
		{
		    "module_code": "CIS018-1",
		    "module_name": "Fundamentals Of Computer Studies",
		    "day": 0,
		    "start": "9:00",
		    "end": "11:00",
		    "length": 2,
		    "length_str": "2 hours",
		    "rooms": 
			[
			    "C016 - CST Teaching Lab"
			],
			"rooms_short": 
			[
			    "C016"
			],
			"staff": 
			[
			    "Sue Brandreth"
			],
			"hash": "be39ce93b2a78f3b73b4e8cbe84559dc",
			"is_valid": true
		}, ...
	]
}
```

### Courses

```
{
    "response_time": 6.5974431037903,
    "error": false,
    "courses": 
	[
		{
		    "id": "BSCCS-S/02AA/1/CIS213/BSc (Hons)/FT",
		    "name": "Computer Science - BSc (Hons) - Ltn - Year 1 Feb FT",
		    "level": "Undergraduate Year 1",
		    "dept_id": "12600",
		    "department": 
		    {
		        "id": "12600",
		        "name": "Department of Computer Science and Technology"
		    },
		    "session_url": "http://whatever/sessions?dept=12600&course=BSCCS-S%2F02AA%2F1%2FCIS213%2FBSc+%28Hons%29%2FFT&level=Undergraduate+Year+1",
		    "name_start": "Computer Science",
		    "name_end": "BSc (Hons) - Ltn - Year 1 Fe - Ltn - Year 1 Feb FT"
		}, ...
	],
	"departments" : 
	[
		{
		    "id": "12600",
		    "name": "Department of Computer Science and Technology"
		}, ...
	]
}
```
