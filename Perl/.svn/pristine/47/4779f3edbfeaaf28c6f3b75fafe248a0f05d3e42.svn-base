##
## CWB registry entry for corpus DICKENS
##


##
## Corpus Header
##

# a long descriptive name for the corpus (not used by the CWB tools)
NAME "IMS Corpus Workbench Demo Corpus (Novels by Charles Dickens)"

# CWB name of the corpus
# - must be lowercase (ID for corpus DICKENS is "dickens")
# - must be identical to filename of registry entry
ID   test	# name modified by CWB::RegistryFile module

# data file directory (relative or absolute path)
HOME /corpora/Registry/DemoCorpus/data

# corpus properties provide additional information about the corpus:
##:: charset = "latin1"	# change if your corpus uses different charset
##:: language = "en"	# insert ISO code for language (de, en, fr, ...)
##:: valid = "FALSE"
#========================================================================#

# POSITIONAL ATTRIBUTES

ATTRIBUTE word
ATTRIBUTE pos
ATTRIBUTE lemma


##
## s-attributes (structural markup)
##

# <file name=".."> ... </file>
# (no recursive embedding allowed)
STRUCTURE file
STRUCTURE file_name	# [annotations]

# <novel title=".."> ... </novel>
# (no recursive embedding allowed)
STRUCTURE novel
STRUCTURE novel_title	# [annotations]

# <titlepage> ... </titlepage>
STRUCTURE titlepage

# <book num=".."> ... </book>
# (no recursive embedding allowed)
STRUCTURE book
STRUCTURE book_num	# [annotations]

# <chapter num=".." title=".."> ... </chapter>
# (no recursive embedding allowed)
STRUCTURE chapter
STRUCTURE chapter_num	# [annotations]
STRUCTURE chapter_title	# [annotations]

# <title len=".."> ... </title>
# (no recursive embedding allowed)
STRUCTURE title
STRUCTURE title_len	# [annotations]

# <p len=".."> ... </p>
# (no recursive embedding allowed)
# FURTHER COMMENTS ADDED BY CWB::RegistryFile
STRUCTURE p
STRUCTURE p_len	# [annotations]

# <s len=".."> ... </s>
# (no recursive embedding allowed)
STRUCTURE s
STRUCTURE s_len	# [annotations]

# some comments on this block of attributes
#   with indentation
STRUCTURE chunk
STRUCTURE chunk1
STRUCTURE chunk2
STRUCTURE chunk3
ALIGNED test-fr	# one alignment per corpus pair
