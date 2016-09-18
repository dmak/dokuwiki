.PHONY: all proper dist print

SCRIPTS := syntax/maps.php script.js style.css
CONFIG := conf/default.php

# Date-based:
#VERSION := `date +%F`

# Plugin based (note double escaping of variable $1 because it is evaluated twice by two shells):
VERSION := `perl -ne "/'date'\s+=>\s+'(\d{4,4}-\d{2,2}-\d{2,2})'/ && print qq{\\$$1}" < syntax/maps.php`

PACKAGE := google-$(VERSION).tar.bz2

all: make_package print_scripts

proper:
	chmod 640 $(SCRIPTS) $(CONFIG)
	chown dmitry:www-data $(SCRIPTS) $(CONFIG)

dummy:
	bash -c "echo -e test$$hello\n"

dist: proper
#	Generate VERSION
	@echo $(VERSION) > VERSION
#	Spoil my Google API key:
#	perl -pe "s/(google_api_key.+?)('.+';)$$/\$$1\U\$$2/" < $(CONFIG) > $(CONFIG).tmp
#	mv $(CONFIG).tmp $(CONFIG)
	tar --exclude=.svn --exclude=.gitignore --exclude=Makefile --exclude=*.bz2 --exclude=*.tmp -C .. -cjf $(PACKAGE) google
#	svn revert conf/default.php
	rm VERSION

print:
	for file in $(SCRIPTS); do echo "==== $$file ===="; echo; echo "<code|$$file>"; perl -pe 's/\t/  /g' < $$file; echo '</code>'; echo; done > out.build
