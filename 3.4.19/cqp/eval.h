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

#ifndef _cqp_eval_h_
#define _cqp_eval_h_


#include "regex2dfa.h"
#include "corpmanag.h"
#include "symtab.h"
#include "options.h"


#define repeat_inf  -1  /**< constant which indicates 'infinite repetition' (actually, repetition up to hard_boundary) @see hard_boundary */
#define repeat_none -2  /**< constant which indicates 'no repetition'       */

/** Number of AVStructures to put in each Patternlist */
#define MAXPATTERNS 5000

/** maximum number of EvalEnvironments in the global array */
#define MAXENVIRONMENT 10


/*
 * definition of the evaluation tree of boolean expressions
 */


/**
 * Labels a boolean operation.
 */
enum b_ops {
             b_and,      /**< boolean and operator           */
             b_or,       /**< boolean or operator            */
             b_implies,  /**< boolean implication (->) operator */
             b_not,      /**< boolean negation               */

             cmp_gt,     /**< compare: greater than          */
             cmp_lt,     /**< compare: less than             */
             cmp_get,    /**< compare: greater or equal than */
             cmp_let,    /**< compare: less or equal than    */
             cmp_eq,     /**< compare: equal                 */
             cmp_neq,    /**< compare: not equal             */

             cmp_ex      /**< is value present? bool exprs   */
};


/**
 * Labels the type of a "wordform" comparison: normal, regular expression, or cpos id ref.
 */
enum wf_type {
    NORMAL,  /**< type of comparison = normal. */
    REGEXP,  /**< type of comparison = regex. */
    CID      /**< type of comparison = compare as integer corpus ID refs. */
};
/*
 * NB. An ancient comment in the eval.c code says, regarding CID:
 *        "that's quick & dirty. We should have a cidref node"
 * IE, the CID type of "wordform" comparison was a stopgap originally -
 * patching integer comparison into the strign comparison.
 */

/**
 * Labels the type of a boolean node.
 */
enum bnodetype {
                 bnode,                 /**< boolean evaluation node            */
                 cnode,                 /**< constant node                      */
                 func,                  /**< function call                      */
                 sbound,                /**< structure boundary (open or close) */
                 pa_ref,                /**< reference to positional attribute  */
                 sa_ref,                /**< reference to structural attribute  */

                 string_leaf,           /**< string constant */
                 int_leaf,              /**< integer constant */
                 float_leaf,            /**< float constant */

                 id_list,               /**< list of IDs */
                 var_ref                /**< variable reference */
               };

/**
 * Union of structures underlying the Constraint / Constrainttree objects.
 *
 * Each Constraint is a node in the Constrainttree, i.e. a single element of a compiled CQP query.
 */
typedef union c_tree {

  /** The type of this particular node.
   * Allows the type member of the other structures within the union to be accessed. */
  enum bnodetype type;

  /** "standard" operand node in the evaluation tree; type is "bnode" */
  struct {
    enum bnodetype type;                  /**< must be bnode                     */
    enum b_ops     op_id;                 /**< identifier of the bool operator   */
    union c_tree  *left,                  /**< points to the first operand       */
                  *right;                 /**< points to the second operand,
                                               if present                        */
  }                node;

  /** "constant" node in the evaluation tree */
  struct {
    enum bnodetype type;                  /**< must be cnode                     */
    int            val;                   /**< Value of the constant: 1 or 0 for true or false */
  }                constnode;

  /** function call (dynamic attribute), type is "func" */
  struct {
    enum bnodetype type;                  /**< must be func                  */
    int            predef;
    Attribute     *dynattr;
    struct _ActualParamList *args;        /**< arguments of the function     */
    int            nr_args;               /**< nr of arguments for this call */
  }                func;

  /** structure boundary */
  struct {
    enum bnodetype type;                  /**< must be sbound                */
    Attribute     *strucattr;             /**< the attribute which corresponds to the structure */
    Boolean        is_closing;            /**< True if closing tag, False for opening tag */
  }                sbound;

  /** reference to positional attribute */
  struct {
    enum bnodetype type;                  /**< must be pa_ref */
    LabelEntry     label;                 /**< may be empty (NULL) */
    Attribute     *attr;                  /**< the P-attribute we are referring to */
    int            delete;                /**< delete label after using it ? */
  }                pa_ref;

  /**
   * reference to structural attribute.
   *
   * If label is empty, this checks if the current position is at start
   * or end of structural_attribute and returns INT value (this is kept for
   * backward compatibility regarding lbound() and rbound() builtins; the new
   * syntax is to use {s} and {/s}, which are represented as 'Tag' nodes.
   *
   * If label is non-empty, the referenced S-attribute must have values, and
   * the value of the enclosing region is returned as a string; in short,
   * values of attributes can be accessed through label references .
   */
  struct {
    enum bnodetype type;                  /**< must be sa_ref */
    LabelEntry     label;                 /**< may be empty (NULL) */
    Attribute     *attr;                  /**< the s-attribute we are referring to */
    int            delete;                /**< delete label after using it ? */
  }                sa_ref;

  struct {
    enum bnodetype type;                  /**< must be var_ref */
    char          *varName;
  }                varref;

  struct {
    enum bnodetype type;                  /**< must be id_list */
    Attribute     *attr;
    LabelEntry     label;                 /**< may be empty (NULL) */
    int            negated;
    int            nr_items;
    int           *items;                 /**< an array of item IDs of size nr_items */
    int            delete;                /**< delete label after using it ? */
  }                idlist;

  /** constant (string, int, float, ...) */
  struct {
    enum bnodetype type;                  /**< string_leaf, int_leaf, or float_leaf */

    int            canon;                 /**< canonicalization mode (i.e. flags)         */
    enum wf_type   pat_type;              /**< pattern type: normal wordform or reg. exp. */
    CL_Regex       rx;                    /**< compiled regular expression (using CL frontend) */

    /** Union containing the constant type. */
    union {
      char        *sconst;               /**< operand is a string constant.           */
      int          iconst;               /**< operand is a integer constant.          */
      int          cidconst;             /**< operand is {?? corpus position?? corpus lexicon id??} constant */
      double       fconst;               /**< operand is a float (well, double) constant */
    }              ctype;
  }                leaf;
} Constraint;

/**
 * The Constrainttree object.
 */
typedef Constraint *Constrainttree;


/**
 * The ActualParamList object: used to build a linked list of parameters,
 * each one of which is a Constrainttree.
 */
typedef struct _ActualParamList {
  Constrainttree param;
  struct _ActualParamList *next;
} ActualParamList;


/** Enumeration specifying different types of eval-tree node. */
enum tnodetype {
    node,           /**< This is a branching node in a "normal" (regex) query tree. */
    leaf,           /**< This is a terminal node in a "normal" (regex) query tree. */
    meet_union,     /**< This is a tree for a MU (meet-union) query. */
    tabular         /**< This is a tree for a TAB (tabular) query. */
};

/** Enumeration of regular expression operations (for token-level regex) */
enum re_ops  {
    re_od_concat,   /**< regex operation: order dependent concatenation   */
    re_oi_concat,   /**< regex operation: order independent concatenation */
    re_disj,        /**< regex operation: disjunction               */
    re_repeat       /**< regex operation: repetition, i.e. ({n} and {n,k})  */
};

/** Symbols for the two operations of meet-union queries, that is meet and union! */
enum cooc_op {
    cooc_meet,      /** MU operation: meet */
    cooc_union      /** MU operation: union */
};


/**
 * Evaltree object: structure for a compiled CQP query.
 */
typedef union e_tree *Evaltree;


/* cross-check tree.h after changes of this data structure!!!
 * also check the print commands in tree.c */

/**
 * Underlying union for the Evaltree object.
 *
 * Consists of a number of anonymous-type structures
 * (node, leaf, cooc, tab_el) that can be found in a tree.
 *
 * The type member is always accessible.
 *
 * @see tnodetype
 */
union e_tree {

  /** What type of node does this union represent? */
  enum tnodetype type;

  /** node type: node */
  struct {
    enum tnodetype type;
    enum re_ops    op_id;      /**< id_number of the RE operator */
    Evaltree       left,       /**< points to the first argument */
                   right;      /**< points to the second argument -- if it exists. */
    int            min,        /**< minimum number of repetitions.  */
                   max;        /**< maximum number of repetitions.  */
  }                node;

  /** node type: leaf */
  struct {
    enum tnodetype type;
    int            patindex;   /**< index into the patternlist */
  }                leaf;

  /** node type: meet_union co-occurrence */
  struct {
    enum tnodetype type;
    enum cooc_op   op_id;
    int            lw, rw;
    Attribute     *struc;
    Evaltree       left,
                   right;
  }                cooc;

  /** node type: tabular */
  struct {
    enum tnodetype type;
    int patindex;              /**< index into the pattern list */
    int min_dist;              /**< minimal distance to next pattern */
    int max_dist;              /**< maximal distance to next pattern */
    Evaltree       next;       /**< next pattern */
  }                tab_el;

};

/* definition of the patternlist, which builds the 'character set' for the
 * regular expressions of wordform patterns
 */

typedef enum _avstype {
  Pattern, Tag, MatchAll, Anchor
} AVSType;

typedef enum target_nature {
  IsNotTarget = 0, IsTarget = 1, IsKeyword = 2
} target_nature;

/**
 * The AVStructure object.
 *
 * A union of structures with the type member always accessible.
 */
typedef union _avs {

  /** What type of AV structure does this union represent? */
  AVSType type;

  /** a matchall item */
  struct {
    AVSType type;                /* set to MatchAll */
    LabelEntry label;
    target_nature is_target;     /**< whether pattern is marked as target (= 1) or keyword (= 2) */
    Boolean lookahead;           /**< whether pattern is just a lookahead constraint */
  } matchall;

  /** a constraint tree */
  struct {
    AVSType type;                /* set to Pattern */
    LabelEntry label;
    Constrainttree constraint;
    target_nature is_target;     /**< whether pattern is marked as target (= 1) or keyword (= 2) */
    Boolean lookahead;           /**< whether pattern is just a lookahead constraint */
  } con;

  /** a structure describing an XML tag */
  struct {
    AVSType type;                /* set to Tag */
    int is_closing;
    Attribute *attr;
    char *constraint;            /**< constraint for annotated value of region (string or regexp); NULL = no constraint */
    int flags;                   /**< flags passed to regexp or string constraint (information purposes only) */
    CL_Regex rx;                 /**< if constraint is a regexp, this holds the compiled regexp; otherwise NULL */
    int negated;                 /**< whether constraint is negated (!=, not matches, not contains) */
    LabelEntry right_boundary;   /**< label in RDAT namespace: contains right boundary of constraining region (in StrictRegions mode) */
  } tag;

  /* an anchor point tag (used in subqueries) */
  struct {
    AVSType type;                /* set to Anchor */
    int is_closing;
    FieldType field;
  } anchor;
} AVStructure;

/** AVS is a pointer type for AVStructure */
typedef AVStructure *AVS;

/** Patternlist is an array of AVStructures */
typedef AVStructure Patternlist[MAXPATTERNS];

/* ====================================================================== */

typedef enum ctxtdir { ctxtdir_leftright, ctxtdir_left, ctxtdir_right } ctxtdir;
typedef enum spacet { word, structure } spacet;

/**
 * The Context object.
 *
 * This stores information about context space.
 *
 * "Context" here means the context for evaluation of a query result within
 * a corpus. (???)
 */
typedef struct ctxtsp {
  ctxtdir     direction;     /**< direction of context expansion (if valid).
                                     Might be left, right, or leftright (all ctxtdir_). */
  spacet      space_type;    /**< kind of space (word or structure)         */
  Attribute  *attrib;        /**< attribute representing the structure.     */
  int         size;          /**< size of space in number of structures.    */
  int         size2;         /**< only for meet-context                     */
} Context;


/* ====================================================================== */

/**
 * Global eval environment pointer (actually an array index, not a pointer).
 *
 * eep contains the index of the highest currently-occupied slot within Environment.
 * @see Environment
 */
int eep;

/**
 * The EvalEnvironment object: environment variables for the evaluation of a corpus query.
 */
typedef struct evalenv {
  CorpusList *query_corpus;         /**< the search corpus for this query part */

  int rp;                           /**< index of current range (in subqueries) */

  SymbolTable labels;               /**< symbol table for labels */

  int MaxPatIndex;                  /**< the current number of patterns */
  Patternlist patternlist;          /**< global variable which holds the pattern list */

  Constrainttree gconstraint;       /**< the "global constraint" */

  Evaltree evaltree;                /**< the evaluation tree (with regular exprs) */

  DFA  dfa;                         /**< the regex DFA for the current query */

  int has_target_indicator;         /**< is there a target mark ('@') in the query? */
  LabelEntry target_label;          /**< targets are implemented as a special label "target" now */

  int has_keyword_indicator;        /**< is there a keyword mark (default '@9') in the query? */
  LabelEntry keyword_label;         /**< keywords are implemented as a special label "keyword" */

  LabelEntry match_label;           /**< special "match" label for access to start of match within query */
  LabelEntry matchend_label;        /**< special "matchend" label for access to end of match within query */

  Context search_context;           /**< the search context (within...) */

  Attribute *aligned;               /**< the attribute holding the alignment info */

  int negated;                      /**< 1 iff we should negate alignment constr */

  MatchingStrategy matching_strategy; /**< copied from global option unless overwritten by (?...) directive */

} EvalEnvironment;

/**
 * EEPs are EvalEnvironment pointers.
 */
typedef EvalEnvironment *EEP;

/** A global array of EvalEnvironment structures */
EvalEnvironment Environment[MAXENVIRONMENT];

EEP CurEnv, evalenv;

/* ---------------------------------------------------------------------- */

Boolean eval_bool(Constrainttree ctptr, RefTab rt, int corppos);

/* ==================== the three query types */

void cqp_run_query(int cut, int keep_old_ranges);

void cqp_run_mu_query(int keep_old_ranges, int cut_value);

void cqp_run_tab_query();


/* ==================== Functions for working with the global array of EvalEnvironments.  */

int next_environment();

int free_environment(int thisenv);

void show_environment(int thisenv);

void free_environments();

#endif
