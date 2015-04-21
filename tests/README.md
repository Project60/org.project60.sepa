The tests are intended to be invoked with `civix test`, which is (somewhat)
documented at
<http://wiki.civicrm.org/confluence/display/CRMDOC/Create+a+Module+Extension#CreateaModuleExtension-Addaunit-testclass>.

In essence, these steps are necessary to get everything set up properly:

  * Install civix. You can get [civix](https://github.com/totten/civix) alone,
    or along with other useful tools with the [CiviCRM
    Buildkit](https://github.com/civicrm/civicrm-buildkit).

  * Get a working CiviCRM instance ("site") that you can link with civix. This
    can be your regular devolpment/testing instance you also use for other stuff
    -- running tests won't make any changes to it.

  * "Link" the instance with civix, i.e. make it known to civix so it can
    connect to it. (I'm not sure about the exact steps required -- see the
    documentation...)

  * Prepare the instance for CiviTest, as described at
    <http://wiki.civicrm.org/confluence/display/CRM/Setting+up+your+personal+testing+sandbox+HOWTO>.

    Assuming you are using an already existing installation, the necessary steps
    boil down to this:

      + Create a separate CiviCRM database instance for the tests. This is
        important, as tests will destroy the data in the test DB; but the
        instance civix is linked to needs to remain intact -- so you can *not*
        just use the main DB, even if you don't need the CiviCRM instance for
        anything else.

      + Fill the DB with the CiviCRM table structure.

      + Possibly fill it with the generated CiviCRM data. (I'm not sure whether
        this is really necessary -- the testsuite will clear/repopulate it before
        the tests anyways...)

      + Set up `civicrm/tests/phpunit/CiviTest/civicrm.settings.local.php` on the
        linked site.

  * The `civix test` documentation mentions that the extension to be tested
    needs to be installed on the linked site, which will make civix
    automatically install it on the test DB as well before running the tests.
    This is actually *not* the case for us: some of the tests will actually rely
    on the extension *not* being automatically installed. (Also, I believe the
    extension installation is actually broken in civix right now...) So make
    sure the linked site *doesn't* have the extension installed -- or hack civix
    to ignore it...

    Note that "not installed" in this case means that it's not activated (marked
    as "installed") in the CiviCRM extension manager. The actual code however
    needs to be available in the extension folder, so the tests can activate it
    when necessary.

    Also, to make CiviCRM find the code reliably, it may be necessary to put (or
    link) it into `civicrm/tools/extensions/`, rather than the explicitly
    configured extension folder that is used for extensions installed through
    the UI...

Now it should be possible to run test for individual classes with `civix test
<classname>` (from the extension's root directory), or run all available tests
with just `civix test`.
