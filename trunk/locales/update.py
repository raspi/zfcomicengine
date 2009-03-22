# vim: set et ts=4 sw=4 fenc=ascii nomod:

import fnmatch
import os
import subprocess
import sys

PACKAGE = "zfComicEngine"
VERSION = "1.0.0"
COPYRIGHT = "raspi"
EMAIL = ""

def listfiles(dir, pattern = None):
  result = []
  for root, dirs, files in os.walk(dir):
    for name in files:
      fn = os.path.join(root, name)
      if not pattern or fnmatch.fnmatch(fn, pattern):
        result.append(fn)
  return result

def execute(cmd):
  PIPE = subprocess.PIPE
  proc = subprocess.Popen(cmd, shell = True, stdout = PIPE, stderr = PIPE)
  stdout, stderr = proc.communicate()
  if proc.returncode != 0:
    return False
  return True

if __name__ == "__main__":
  infile = os.path.join("POTFILES.in")
  potfile = os.path.join("default.pot")
  
  phpfiles = listfiles(os.path.join("..", "application"), "*.php")
  phtmlfiles = listfiles(os.path.join("..", "application"), "*.phtml")
  classfiles = listfiles(os.path.join("..", "classes"), "*.php")

  # create POTFILES.in
  srcfiles = []

  for i in phpfiles:
    srcfiles.append(i)

  for i in phtmlfiles:
    srcfiles.append(i)
    
  for i in classfiles:
    srcfiles.append(i)

  file(infile, "w").write("\n".join(srcfiles))

  # create .pot file
  args = {"package": PACKAGE,
          "version": VERSION,
          "copyright": COPYRIGHT,
          "bugs": EMAIL,
          "target": potfile,
          "source": infile}

  cmd = "xgettext -L php --from-code=utf8 --package-name=\"%(package)s\" --package-version=%(version)s --msgid-bugs-address=%(bugs)s --sort-by-file --force-po --files-from=%(source)s --output=%(target)s" % args
  run = execute(cmd)
  if not run:
    print "Error executing command %s" % cmd
    print run
    sys.exit(0)

  # update existing .po files
  pofiles = listfiles(".", "*.po")
  for fn in pofiles:
    args = {"target": fn, "source": potfile}
    cmd = "msgmerge --backup=none -U %(target)s %(source)s" % args
    if not execute(cmd):
      sys.exit("Error executing command %s" % cmd)
