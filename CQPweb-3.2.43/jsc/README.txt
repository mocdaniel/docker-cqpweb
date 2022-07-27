NOTES on JavaScript
===================

- CQPweb requires the jQuery library to support dynamic HTML.
- jQuery is provided internally, rather than being downloaded from an external source.
  - Advantages: we are programing against a known version (3.3.1 as of most recently),
    actual internet connection not needed, we can update the internal copy at intervals
    of our own choice.
  - Minified copy sourced from https://code.jquery.com/jquery-3.3.1.min.js 
    viahttps://jquery.com/download/ . 
  - Disadvantages: there is a (one-time) large download.
  - It can be found in the file "./jquery.js". 

OTHER external libraries, and their versions.

- d3: not currently stored locally.

- wordcloud2.js : local, copy of v 1.1.0, Feb 2018. Minified copy sourced from 
  https://cdnjs.cloudflare.com/ajax/libs/wordcloud2.js/1.1.0/wordcloud2.min.js 
  