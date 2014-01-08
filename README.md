MStar-Calendar
==============

Calendar plugin for WordPress with full recurring events support

###Features:

* Some examples of frequency methods available:
    * Mondays, Wednesdays, Thursdays from 7:00 pm - 8:15 pm, January 12th through March 2nd
    * Every 3rd Tuesday, all day
    * Last Sunday of each month, starting at 10:15 am 
    * Etc.
* Each event can have its own list of exception dates; i.e. cancellations.
* A "master list" of exceptions can also apply to each event; i.e. holidays.
* Cached results for faster rendering
* jQuery-driven front end with searh and taxonomy support

###To Use:
* Upload Zip to `/plugins/` directory, extract and activate.
* Add shortcode to a page! `[mstar_calendar history="12" future="12" cache_hours="24"]` where `history` is number of past months that should be included, `future` is months ahead, and `cache_hours` is the length of time the cache should wait before dumping (you can also manually dump the cache in Events->Options).
* If you make a `single-event.php` in your theme directory, it'll use it, otherwise it'll make its own on the fly!

###Roadmap:
* Make separate table for Events, Child Events

License: GPLv2 or later

License URI: http://www.gnu.org/licenses/gpl-2.0.html
