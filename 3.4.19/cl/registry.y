/* 
 *  IMS Open Corpus Workbench (CWB)
 *  Copyright (C) 1993-2006 by IMS, University of Stuttgart
 *  Copyright (C) 2007-     by the respective contributers (see file AUTHORS)
 * 
 *  This program is free software; you can redistribute it and/or modify it
 *  under the terms of the GNU General Public License as published by the
 *  Free Software Foundation; either version 2, or (at your option) any later
 *  version.
 * 
 *  This program is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General
 *  Public License for more details (in the file "COPYING", or available via
 *  WWW at http://www.gnu.org/copyleft/gpl.html).
 */

/**
 * This file contains the Bison grammar for the registry parser.  
 */
%{

#include <ctype.h>

#include "globals.h"

#include "corpus.h"
#include "attributes.h"

/*
 * note that flex/bison run with "creg" specified as their prefix. So cregerror is yyerror, etc. 
 */

extern int creglex();

Corpus *cregcorpus = NULL;
Attribute *cregattrib = NULL;

char cregestring[1024];

/* ====================================================================== */

DynArg *makearg(char *type_id);

#define cregSetAttrComponentPath(attr, cid, path) \
{ \
  if (!declare_component(attr, cid, path)) { \
    sprintf(cregestring, "Component %s with path %s declared twice" \
            " (or internal error)", cid_name(cid), path); \
    cl_free(path); \
    cregerror(cregestring); \
  } \
}

void cregerror_cleanup(char *message)
{
  fprintf(stderr, "REGISTRY ERROR (%s/%s): %s\n", cregin_path, cregin_name, message);

  if (cregattrib)
    cl_delete_attribute(cregattrib);
  cregattrib = NULL;

  if (cregcorpus != NULL)
    cl_delete_corpus(cregcorpus);
  cregcorpus = NULL;
}

#define cregerror(message) { cregerror_cleanup(message); YYERROR; }

/* ====================================================================== */

%}


/* 
 * SETTING THE PREFIX FOR EXPORTED PARSER SYMBOLS
 * ==============================================
 * 
 * we want all exported symbols to begin with "creg" instead of "yy";
 * this would normally imply the following directive:  
 *
 *         %name-prefix="creg"
 *
 * (though note that they took out the "=" in bison 2.4).
 *
 * However, bison 3 deprecates the above; the b3 version of this command is:
 * 
 *         %define api.prefix {creg}
 *
 * which also is more comprehensive in terms of the symbols it alters
 * (YYSTYPE, YYDEBUG, etc. ; see the bison NEWS file).
 * 
 * bison 2 is still in wide use. But, using the %name-prefix directive
 * triggers a deprecation warning message in bison 3. (Meanwhile the 
 * -Wno-deprecation option isn't supported in bison 2.) So although it
 * would amke sense to have the declaration here, it is instead in the
 * Makefile (as -p creg).
 */

%union {
  char    *strval;
  int      ival;
  void    *args;
  void    *attr;

  IDList   idlist;

  struct {
    int status;
    char *path;
  } storage;
}

%token NAME_SYM
%token ID_SYM
%token INFO_SYM
%token HOME_SYM

%token ATTRIBUTE_SYM

%token DIR_SYM
%token CORPUS_SYM
%token REVCORP_SYM
%token REVCIDX_SYM
%token FREQS_SYM
%token LEXICON_SYM
%token LEXIDX_SYM
%token LEXSRT_SYM

%token STRUCTURE_SYM
%token ALIGNED_SYM
%token DYNAMIC_SYM
%token DOTS_SYM

%token IGNORE_SYM               /* ignore MAPTABLEs and NGRAMs, which are present in many corpora  */

%token ADMIN_SYM
%token ACCESS_SYM
%token USER_SYM
%token GROUP_SYM
%token ASSERT_SYM
%token HOST_SYM

%token PROPERTY_SYM


%token <strval> IDENTIFIER
%token <strval> STRING
%token <ival> NUMBER

%type <strval> string
%type <strval> id
%type <strval> OptInfo
%type <strval> IDDecl
%type <strval> OptHome OptAdmin
%type <strval> NameDecl
%type <strval> path

%type <idlist> IDList

%type <storage> StorageSpec

%type <args> ArgList
%type <args> SingleArg

%type <attr> Attribute

%%

Registry        : /* eps */         { cregcorpus = (Corpus *)cl_malloc(sizeof(Corpus)); 
                                      cregcorpus->attributes = NULL;
                                      cregcorpus->name = NULL;
                                      cregcorpus->id = NULL;
                                      cregcorpus->path = NULL;
                                      cregcorpus->charset = latin1;  /* default charset is latin1 */
                                      cregcorpus->properties = NULL;
                                      cregcorpus->info_file = NULL;
                                      cregcorpus->admin = NULL;
                                      cregcorpus->groupAccessList = NULL;
                                      cregcorpus->hostAccessList = NULL;
                                      cregcorpus->userAccessList = NULL;
                                      cregcorpus->registry_dir = NULL;
                                      cregcorpus->registry_name = NULL;
                                      cregcorpus->nr_of_loads = 1;
                                      cregcorpus->next = NULL;
                                    }
                  Header
                  Declaration       { if (cregcorpus->attributes == NULL) {
                                        cregerror("Illegal corpus declaration -- no attributes defined"); 
                                      }
                                    }
                | error             { cregerror_cleanup("Error parsing the main Registry structure."); YYABORT; }
                ;

Declaration     : Attributes        { /* nop */ }
                  ;

Header          : NameDecl
                  IDDecl 
                  OptHome
                  OptInfo           { cregcorpus->name      = $1;
                                      cregcorpus->id        = $2;
                                      cregcorpus->path      = $3;
                                      cregcorpus->info_file = $4;
                                    }
                  OptAdmin
                  OptHostAccessClause
                  OptUserAccessClause
                  OptGroupAccessClause
                  OptProperties
                ;

OptInfo         : INFO_SYM path     { $$ = $2; }
                | /* eps */         { $$ = NULL; }
                ; 

OptHome         : HOME_SYM path     { $$ = $2; }
                | /* eps */         { $$ = NULL; }
                ; 

OptAdmin        : ADMIN_SYM id      { cregcorpus->admin = $2; }
                | /* eps */         { cregcorpus->admin = NULL; }
                ;

OptUserAccessClause: USER_SYM 
                  '{' IDList '}'    { cregcorpus->userAccessList = $3; }
                  | /* eps */       { cregcorpus->userAccessList = NULL; }
                ;

OptGroupAccessClause: GROUP_SYM 
                  '{' IDList '}'    { cregcorpus->groupAccessList = $3; } 
                | /* eps */         { cregcorpus->groupAccessList = NULL; }
                ;

OptHostAccessClause: HOST_SYM 
                  '{' IDList '}'    { cregcorpus->hostAccessList = $3; }
                | /* eps */         { cregcorpus->hostAccessList = NULL; }
                ;

NameDecl        : NAME_SYM string   { $$ = $2; }
                ;

IDDecl          : ID_SYM id         { $$ = $2; }
                | /* eps */         { $$ = NULL; }
                ;
                   
Attributes      : Attribute         { 
                                      /* declare components which are not yet declared for local attrs. */
                                      if ((((Attribute *)$1)->any.path == NULL) && (cregcorpus->path != NULL))
                                        ((Attribute *)$1)->any.path = cl_strdup(cregcorpus->path);
                                      declare_default_components((Attribute *)$1);
                                    }
                  Attributes
                | IGNORE_SYM id id StorageSpec {}
                  /* *** this rule helps us ignore MAPTABLE and NGRAM attributes *** */
                  Attributes
                | /* eps */
                  ;

Attribute       : ATTRIBUTE_SYM 
                  id                { 
                                      if ((cregattrib = setup_attribute(cregcorpus, $2, ATT_POS, NULL)) == NULL) {
                                        sprintf(cregestring, 
                                                "Positional attribute %s declared twice -- "
                                                "semantic error", $2);
                                        cl_free($2);
                                        cregerror(cregestring);
                                      }
                                    }
                  AttrBody          { $$ = cregattrib; cregattrib = NULL; }

                | ALIGNED_SYM id StorageSpec
                                    { if (($$ = setup_attribute(cregcorpus, $2, ATT_ALIGN, NULL)) == NULL) {
                                        sprintf(cregestring, "Alignment attribute %s declared twice -- "
                                                "semantic error", $2);
                                        cl_free($2);
                                        cl_free($3.path);
                                        cregerror(cregestring);
                                      }

                                      ((Attribute *)$$)->align.path = $3.path;
                                    }
                | STRUCTURE_SYM id StorageSpec
                                    { if (($$ = setup_attribute(cregcorpus, $2, ATT_STRUC, NULL)) == NULL) {
                                        sprintf(cregestring, "Structure attribute %s declared twice -- "
                                                "semantic error", $2);
                                        cl_free($2);
                                        cl_free($3.path);
                                        cregerror(cregestring);
                                      }

                                      ((Attribute *)$$)->struc.path = $3.path;
                                    }
                | DYNAMIC_SYM id '(' 
                  ArgList 
                  ')' ':' SingleArg string  
                                    { if (($$ = setup_attribute(cregcorpus, $2, ATT_DYN, NULL)) == NULL) {

                                        DynArg *a;

                                        sprintf(cregestring, "Dynamic attribute %s declared twice -- "
                                                "semantic error", $2);
                                        cl_free($2);
                                        cl_free($7);
                                        cl_free($8);

                                        while ($4 != NULL) {
                                          a = (DynArg *)$4;
                                          $4 = ((DynArg *)a)->next;
                                          cl_free(a);
                                        }

                                        cregerror(cregestring);
                                      }

                                      ((Attribute *)$$)->dyn.arglist = $4;
                                      ((Attribute *)$$)->dyn.res_type = ((DynArg *)$7)->type;
                                      free($7);
                                      ((Attribute *)$$)->dyn.call = $8;

                                      ((Attribute *)$$)->dyn.path = NULL;
                                    }
                ;

StorageSpec     : path              { $$.path = $1; 
                                    }
                | /* eps */         { $$.path = NULL;
                                    }
                ;

AttrBody        : OptFieldDefs      { assert(cregattrib != NULL);
                                      if ((cregattrib->any.path == NULL) &&
                                          (cregcorpus->path != NULL))
                                        cregattrib->any.path = cl_strdup(cregcorpus->path);
                                    }
                | path              { assert(cregattrib != NULL);
                                      cregattrib->any.path = $1; 
                                    }
                ;

ArgList         : SingleArg         { $$ = $1; }
                | ArgList ',' SingleArg  
                                    { 
                                      DynArg *last;
                                      assert($1 != NULL);
                                      last = $1; 
                                      while (last->next != NULL) last = (DynArg *)last->next;
                                      
                                      last->next = $3; 
                                      $$ = $1; 
                                    }
                ; 

SingleArg       : id                { $$ = (DynArg *)makearg($1); 
                                      if ($$ == NULL) {
                                        sprintf(cregestring, "Illegal argument type %s or "
                                                "not enough memory -- FATAL ERROR", $1);
                                        cregerror(cregestring);
                                      }
                                    }
                | DOTS_SYM          { $$ = (DynArg *)makearg("VARARG"); 
                                      if ($$ == NULL)
                                        cregerror("Internal error while parsing variable "
                                                  "argument list -- FATAL ERROR");
                                    }
                ; 

OptFieldDefs    : '{' FieldDefs '}'
                | /* eps */
                ;

FieldDefs       : FieldDef FieldDefs
                | /* eps */
                ;

FieldDef        : DIR_SYM path      { cregSetAttrComponentPath(cregattrib, CompDirectory,    $2); }
                | CORPUS_SYM path   { cregSetAttrComponentPath(cregattrib, CompCorpus,       $2); }
                | REVCORP_SYM path  { cregSetAttrComponentPath(cregattrib, CompRevCorpus,    $2); }
                | REVCIDX_SYM path  { cregSetAttrComponentPath(cregattrib, CompRevCorpusIdx, $2); }
                | FREQS_SYM path    { cregSetAttrComponentPath(cregattrib, CompCorpusFreqs,  $2); }
                | LEXICON_SYM path  { cregSetAttrComponentPath(cregattrib, CompLexicon,      $2); }
                | LEXIDX_SYM path   { cregSetAttrComponentPath(cregattrib, CompLexiconIdx,   $2); }
                | LEXSRT_SYM path   { cregSetAttrComponentPath(cregattrib, CompLexiconSrt,   $2); }
                ;

path            : id                { $$ = $1; }
                | string            { $$ = $1; }
                ;

id              : IDENTIFIER        { $$ = $1; }
                | NUMBER            { char *nr;
                                      nr = (char *)cl_malloc(16);
                                      sprintf(nr, "%d", $1);
                                      $$ = nr;
                                    }
                ;

IDList          : IDList id         { IDList n;
                                      n = (IDList)cl_malloc(sizeof(IDBuf));
                                      n->next = $1;
                                      n->string = $2;
                                      $$ = n;
                                    }
                | /* eps */         { $$ = NULL; }
                ;

string          : STRING            { $$ = $1; } 
                ;

OptProperties   : OptProperties Property
                | /* eps */
                ;

Property        : PROPERTY_SYM IDENTIFIER '=' STRING
                                        {
                                          add_corpus_property(cregcorpus, $2, $4);
                                        }
                | PROPERTY_SYM IDENTIFIER '=' id
                                        { /* allow IDs and numbers without quotes */
                                          add_corpus_property(cregcorpus, $2, $4);
                                        }
                ;
                                                


%%



