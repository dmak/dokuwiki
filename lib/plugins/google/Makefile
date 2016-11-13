.PHONY: all proper dist print

SCRIPTS := syntax/maps.php script.js style.css
CONFIG := conf/default.php

# Date-based:
#VERSION := `date +%F`

# Plugin based (note double escaping of variable $1 because it is evaluated twice by two shells):
VERSION := $(shell perl -ne "/'date'\s+=>\s+'(\d{4,4}-\d{2,2}-\d{2,2})'/ && print qq{\$$1}" < syntax/maps.php)

PACKAGE := google-$(VERSION).tar.bz2

all: make_package print_scripts

proper:
	chmod 640 $(SCRIPTS) $(CONFIG)
#	chown dmitry:www-data $(SCRIPTS) $(CONFIG)

dummy:
	bash -c "echo -e test$$hello\n"

dist: proper
	@if [ "`git status --porcelain 2>/dev/null | grep -v $(CONFIG) | wc -l`" -ne 0 ]; then echo "Directory is dirty. Commit the changes first."; exit 1; fi
	git stash
#	Generate VERSION
	@echo $(VERSION) > VERSION
	exit 1
	sleep 1
	tar --exclude=.svn --exclude=.gitignore --exclude=Makefile --exclude=*.bz2 --exclude=*.tmp -C .. -cjf $(PACKAGE) google
	rm VERSION
	git tag google_$(VERSION)
	git stash pop
	@echo "Now run 'git push origin google_$(VERSION)'"

print:
	for file in $(SCRIPTS); do echo "==== $$file ===="; echo; echo "<code|$$file>"; perl -pe 's/\t/  /g' < $$file; echo '</code>'; echo; done > out.build
