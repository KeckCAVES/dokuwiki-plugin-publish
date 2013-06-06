
Publish -- DokuWiki Plugin

Add a publishing process to DokuWiki pages.
(Forked from the plugin by Jarrod Lowe.)


== OVERVIEW ==

The publish plugin allows any page to have a 'published' revision, which will
be the revision shown to the public.  Meanwhile, editors can keep changing the
page without affecting what the public sees until they are ready to publish.


== INSTALLATION ==

Install like any other DokuWiki plugin, except for one caveat:

The plugin cannot stop DokuWiki from warning the viewer that s/he is looking
at an old version of the page.  To disable this feature in Dokuwiki, you must
comment out line 243 of the file 'inc/html.php', e.g.:

    ...
    }else{
        //if ($REV) print p_locale_xhtml('showrev'); //removed for publishing
        $html = p_wiki_xhtml($ID,$REV,true);
        $html = html_secedit($html,$secedit);
    ...

The above is current as of DokuWiki 2013-05-10 ("Weatherwax").
