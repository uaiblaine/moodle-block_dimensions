# Continue another user's plan instead of viewing it.
s/\$viewonly = \$this->userid !== \(int\) \$USER->id;/\$viewonly = false;/;
