.PHONY: help remove_css remove_cache flush_cache flush_index proper fix_changetime find_namespaces acronyms create_patch convert_bz2

help:
	@echo "make [help|proper|remove_css|remove_cache|flush_cache|flush_index|fix_changetime|acronyms|find_namespaces|create_patch|convert_bz2]"

proper:
	@echo "Correcting permissions for objects..."
	find data/ -type f -exec chown dmitry.www-data '{}' \; -exec chmod 660 '{}' \;
	find data/ -type d -exec chown dmitry.www-data '{}' \; -exec chmod 770 '{}' \;
	#find lib inc -type f -exec chown dmitry.www-data '{}' \; -exec chmod 640 '{}' \;
	#find lib inc -type d -exec chown dmitry.www-data '{}' \; -exec chmod 750 '{}' \;
	chmod g+w -R conf
	find . -type d -name .svn -o -iname .git -exec chgrp users -R '{}' \;
	find . -type f -name .htaccess -exec chmod 640 '{}' \;

remove_css:
	find data/cache -type f -iname '*.css' | xargs rm -f

remove_cache:
	find data/cache -type f | grep -v '.draft' | xargs rm -f

# Resolve Dropbox conflicts by applying the latest version:
resolve_conflicts:
	find data/ -regextype posix-egrep -regex ".* conflicted .*\.(meta|idx|i|text|xhtml|css|feed)" \
	| sort \
	| perl -ne 'chomp; $$file = $$_; s/(.+) \( [^)]+\)(.+)/$$1$$2/ or die; print "mv \"$$file\" \"$$_\"\n";' \
	| bash

# See http://www.dokuwiki.org/devel:caching
flush_cache:
	touch conf/local.php

flush_index:
	lynx -dump 'http://localhost/w/lib/exe/indexer.php?id=start&debug=1' > /dev/null

# Reindex all pages for fultext search:
reindex:
	cd bin && php indexer.php -c

# See http://www.dokuwiki.org/tips:fixmtime
fix_changetime:
	php bin/fix_changetime.php

acronyms:
	cat conf/acronyms.conf | sort > acronyms.conf
	-diff -u conf/acronyms.conf acronyms.conf
	rm -f acronyms.conf

find_namespaces:
	grep --color=auto -rnHP '\[\[(?!(http|ftp|mailto|irc))[^:|>\]]*:' data/pages/

create_patch:
	find . -iname '*.orig' -o -iname '*.dist' | cut -c3- | while read name; do diff -uN "$$name" `perl -e 'print $$1 if $$ARGV[0] =~ /^(.*?)\.\w+$$/;' "$$name"`; done > all.patch

convert_bz2:
	pushd data && find . -iname *.gz | while read name; do zcat "$$name" | bzip2 -9 > `perl -e 'print $$1 if $$ARGV[0] =~ /^(.*?)\.\w+$$/;' "$$name"`.bz2; done

