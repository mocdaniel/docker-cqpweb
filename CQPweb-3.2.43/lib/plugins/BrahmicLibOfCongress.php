<?php

// class IndologicalRomaniser extends ScriptSwitcherBase implements ScriptSwitcher
class BrahmicLibOfCongress extends ScriptSwitcherBase implements ScriptSwitcher
{
	protected $out = '';
	protected $uc;
	


	
	public function description()
	{
		return "South Asian to Indological Latin script";
	}
	
	public function long_description()
	{
		return "A Switcher that romanises Devanagari-like scripts according to the Library of Congress version of Indological transliteration (and does something as compatible as possible for Perso-Arabic).";
	}
	
	
	protected $map_uncond = [
// 			0x => [],
	];
	
	/* mappings conditional on next character.
	 * inner arrays have different values mappign from different $next chars. 
	 * if the (trailing) 'mrg', then the next char will not itself be processed.
	 * the trainig 'def' is a default value (any other value of $next);
	 * if set, it means that this  
	 */  
	protected $map_cond = [
// 			0x => [ 0x=>[, 'mrg'=>0], 'def'=>[, 'mrg'=>0] ],
	];	
	
	/* mappings with a special rule basis. */ 
	protected function map_unusual($curr, $next, &$skip)
	{
		
		
		return false;
	}
	

	
	/* allows us to shift cahractgerts conditonnally, so that one mapping covers multpile characterrs. */
	protected function get_offset($uc)
	{
		return 0;
	}
}


return;

echo <<<END_OF_C

/*
	This is loctransl, a version of lltransl.

	Note that unlike lltransl, it is NOT lossless.

	What it DOES do is standard indological transliteration.


	It follows the Library of Congress romanisation except where noted.

	NOTE: where LOC conflicts with ISO 15919 I have basically just picked one based on
	what can be rendered easily. Key rule: avoid diacritics wherever possible.

	NOTE: candrabindu and anusvara have single-character equivalents instead of varying.
	(traditional LOC transliterates anusvara as the homorganic nasal of the following stop,
	this is not followed here. traditional LOC transliterates candrabindu as n+cb before
	stops and m+cb elsewhere, this is not followed here)

	NOTE: Urdu has not yet been altered from lltransl

	NOTE: Sinhala has not yet been altered from lltransl

	NOTE: Non-devanagari bits have not yet been altered from lltransl

	NOTE: for now danda and double danda have just been left; stress marks not done 
	yet either

	NOTE: nukta on its own (093c) does not process

	NOTE: vocalic r and retroflex r go to the same (r with subscript dot)
	because Unicode doesn't have single characters for the subscript little circle

	NOTE: x is used for "kh+nukta". Becasue there is no underlined h in Unicode.
	Similarly for "gh+nukta", I'm using g with superscript dot.
*/





/* glyphtype stuff */
#define OTHER 0
#define VIRAMA 1
#define CONSONANT 2
#define IND_VOWEL 3
#define VOWEL 4
int glyphtype(unichar ch);



main(int argc, char *argv[])
{
	FILE *source;
	FILE *dest;

	unichar current, next;
	unichar astr[] = { 0x0061, 0 };
	unichar target[30];

	if (argc != 3)
	{
		cl_error_arguments(3, argc);
		return 1;
	}

	/* open the source file and check for (then discard) directionality character */
	if (!(source = fopen(argv[1], "rb")))
	{
		cl_error_file_open(argv[1]);
		fcloseall();
		return 1;
	}
	if (!( ucheckdir(source) ))
	{
		fputs("Specified source file not recognised as Unicode!\n", stderr);
		fcloseall();
		return 1;
	}

	/* check that the target filename won't be overwritten */
	if (cl_test_file_write(argv[2]))
	{
		fcloseall();
		return 0;
	}
	
	/* open file to write, insert directionality character */
	if( !(dest = fopen(argv[2], "wb")) )
	{
		cl_error_file_open(argv[2]);
		fcloseall();
		return 1;
	}
	if ( fputuc( RIGHTWAY , dest) == UERR )
	{
		cl_error_file_write(argv[2]);
		fcloseall();
		return 1;
	}

	current = 0;
	next = 0;

	while (1)
	{
		/* load a character to next. */
		current = next;
		next = fgetuc(source);

		

		/* cycle past start of file */
		if (!current)
			continue;
		/* check for end of file */
		if (current == UERR)
			break;


		/* adjustments for dev-like scripts */
		/* this doesn't cover script specific additions; those still need to be specified */
		/* and excepted from this subtraction */
		/* Bengali */
		if ( current > 0x0980 && current < 0x09ff )
			current -= 0x80;
		/* Gurmukhi */
		if ( current > 0x0a00 && current < 0x0a7f )
			current -= 0x100;
		/* Gujarati */
		if ( current > 0x0a80 && current < 0x0aff )
			current -= 0x180;
		/* Oriya */
		if ( current > 0x0b00 && current < 0x0b7f )
			current -= 0x200;
		/* Tamil */
		if ( current > 0x0b80 && current < 0x0bff )
			current -= 0x280;
		/* Telugu */
		if ( current > 0x0c00 && current < 0x0c7f )
			current -= 0x300;
		/* Kannada */
		if ( current > 0x0c80 && current < 0x0cff )
			current -= 0x380;
		/* Malayalam */
		if ( current > 0x0d00 && current < 0x0d7f )
			current -= 0x400;

		/* etc. */

		// and Sinhala will need to be completely different! ... see below



		/* look at the character */
		switch (current)
		{
		/* URDU */
		0x060c:
		0x060d => [ 0x002c ] ,
		0x060e => [ 0x002e ] ,
		0x0610:
		0x0611:
		0x0612:
		0x0613:
		0x0614:
		0x0615 => [ 0x0027 ] ,
		0x061b => [ 0x003b ] ,
		0x061f => [ 0x003f ] ,
		0x0621 => [ 0x0027 ] ,
		0x0622 => [ 0x0061 , 0x0061 ] ,
		0x0623 => [ 0x0027 ] ,
		0x0624 => [ 0x0027 , 0x0076 ] ,
		0x0625 => [ 0x0027 ] ,
		0x0626 => [ 0x0027 , 0x0079 ] ,
		0x0627 => [ 0x0061 , 0x0061 ] ,
		0x0628 => [ 0x0062 ] ,
		0x0629 => [ 0x0074 ] ,
		0x062a => [ 0x0074 ] ,
		0x062b => [ 0x0073 ] ,
		0x062c => [ 0x006a ] ,
		0x062d => [ 0x0068 ] ,
		0x062e => [ 0x0078 ] ,
		0x062f => [ 0x0044 ] ,
		0x0630 => [ 0x007a ] ,
		0x0631 => [ 0x0072 ] ,
		0x0632 => [ 0x007a ] ,
		0x0633 => [ 0x0073 ] ,
		0x0634 => [ 0x0073 , 0x0068 ] ,
		0x0635 => [ 0x0073 ] ,
		0x0636 => [ 0x007a ] ,
		0x0637 => [ 0x0074 ] ,
		0x0638 => [ 0x007a ] ,
		0x0639 => [ 0x0040 ] ,
		0x063a => [ 0x0047 ] ,
		0x0640 => [ ] ,
		0x0641 => [ 0x0066 ] ,
		0x0642 => [ 0x0071 ] ,
		0x0643 => [ 0x006b ] ,
		0x0644 => [ 0x006c ] ,
		0x0645 => [ 0x006d ] ,
		0x0646 => [ 0x006e ] ,
		0x0647 => [ 0x0068 ] ,
		0x0648 => [ 0x0076 ] ,
		0x0649 => [ 0x0079 ] ,
		0x064a => [ 0x0079 ] ,
		//0x064b => [ 0x00 ] ,
		//0x064c => [ 0x00 ] ,
		//0x064d => [ 0x00 ] ,
		0x064e => [ 0x0061 ] ,
		0x064f => [ 0x0075 ] ,
		0x0650 => [ 0x0069 ] ,
		//0x0651 => [previous] ,	
		//0x0652 => [ 0x00 ] ,
		0x0653 => [ ] ,
		0x0654 => [ 0x0027 ] ,
		0x0655 => [ 0x0027 ] ,
		0x0660 => [ 0x0030 ] ,
		0x0661 => [ 0x0031 ] ,
		0x0662 => [ 0x0032 ] ,
		0x0663 => [ 0x0033 ] ,
		0x0664 => [ 0x0034 ] ,
		0x0665 => [ 0x0035 ] ,
		0x0666 => [ 0x0036 ] ,
		0x0667 => [ 0x0037 ] ,
		0x0668 => [ 0x0038 ] ,
		0x0669 => [ 0x0039 ] ,
		0x066a => [ 0x0025 ] ,
		0x066b => [ 0x002c ] ,
		0x066c => [ 0x002c ] ,
		0x066d => [ 0x002a ] ,
		0x0670 => [ 0x0061 ] ,
		0x0679 => [ 0x0054 ] ,
		0x067e => [ 0x0070 ] ,
		0x0686 => [ 0x0063 ] ,
		0x0688 => [ 0x0044 ] ,
		0x0691 => [ 0x0052 ] ,
		0x0698 => [ 0x007a , 0x0068 ] ,
		0x06a9 => [ 0x006b ] ,
		0x06af => [ 0x0067 ] ,
		0x06ba => [ 0x007e ] ,
		0x06be => [ 0x0068 ] ,
		0x06c0 => [ 0x0068 , 0x0065 ] ,
		0x06c1 => [ 0x0068 ] ,
		0x06c2 => [ 0x0068 , 0x0065 ] ,
		0x06c3 => [ 0x0074 ] ,
		0x06cc => [ 0x0079 ] ,
		0x06d2 => [ 0x0065 ] ,
		0x06d3 => [ 0x0027 , 0x0065 ] ,
		0x06d4 => [ 0x002e ] ,
		0x06f0 => [ 0x0030 ] ,
		0x06f1 => [ 0x0031 ] ,
		0x06f2 => [ 0x0032 ] ,
		0x06f3 => [ 0x0033 ] ,
		0x06f4 => [ 0x0034 ] ,
		0x06f5 => [ 0x0035 ] ,
		0x06f6 => [ 0x0036 ] ,
		0x06f7 => [ 0x0037 ] ,
		0x06f8 => [ 0x0038 ] ,
		0x06f9 => [ 0x0039 ] ,

		/* DEVANAGARI */
		/*
		0x0901 => [ ord('n') , 0x0310 ] ,
		*/
		0x0901 => [ 0x0148 ] ,
		0x0902 => [ 0x1e41 ] ,
		0x0903 => [ 0x1e25 ] ,
		0x0904 => [ 0x0103 ] ,
		0x0905 => [ ord('a') ] ,
		0x0906 => [ 0x0101 ] ,
		0x0907 => [ ord('i') ] ,
		0x0908 => [ 0x012b ] ,
		0x0909 => [ ord('u') ] ,
		0x090a => [ 0x016b ] ,
		0x090b => [ 0x1e5b ] ,
		0x090c => [ 0x1e37 ] ,
		0x090d => [ 0x00ea ] ,
		0x090e => [ 0x0115 ] ,
		0x090f => [ ord('e') ] ,
		0x0910 => [ ord('a') , ord('i') ] ,
		0x0911 => [ 0x00f4 ] ,
		0x0912 => [ 0x014f ] ,
		0x0913 => [ ord('o') ] ,
		0x0914 => [ ord('a') , ord('u') ] ,
		0x0915 => [ 0x006b ] ,
		0x0916 => [ 0x006b , 0x0068 ] ,
		0x0917 => [ 0x0067 ] ,
		0x0918 => [ 0x0067 , 0x0068 ] ,
		0x0919 => [ 0x1e45 ] ,
		0x091a => [ 0x0063 ] ,
		0x091b => [ 0x0063 , 0x0068 ] ,
		0x091c => [ 0x006a ] ,
		0x091d => [ 0x006a , 0x0068 ] ,
		0x091e => [ 0x00f1 ] ,
		0x091f => [ 0x1e6d ] ,
		0x0920 => [ 0x1e6d , 0x0068 ] ,
		0x0921 => [ 0x1e0d ] ,
		0x0922 => [ 0x1e0d , 0x0068 ] ,
		0x0923 => [ 0x1e47 ] ,
		0x0924 => [ 0x0074 ] ,
		0x0925 => [ 0x0074 , 0x0068 ] ,
		0x0926 => [ 0x0064 ] ,
		0x0927 => [ 0x0064 , 0x0068 ] ,
		0x0928 => [ 0x006e ] ,
		0x0929 => [ 0x1e49 ] ,
		0x092a => [ 0x0070 ] ,
		0x092b => [ 0x0070 , 0x0068 ] ,
		0x092c => [ 0x0062 ] ,
		0x092d => [ 0x0062 , 0x0068 ] ,
		0x092e => [ 0x006d ] ,
		0x092f => [ 0x0079 ] ,
		0x0930 => [ 0x0072 ] ,
		0x0931 => [ 0x1e5f ] ,
		0x0932 => [ 0x006c ] ,
		0x0933 => [ 0x1e37 ] ,
		0x0934 => [ 0x1e3b ] ,
		0x0935 => [ 0x0076 ] ,
		0x0936 => [ 0x015b ] ,
		0x0937 => [ 0x1e63 ] ,
		0x0938 => [ 0x0073 ] ,
		0x0939 => [ 0x0068 ] ,
		0x093c => [ 0x0023 ] ,
		0x093e => [ 0x0101 ] ,
		0x093d => [ 0x2019 ] ,
		0x093f => [ 0x0069 ] ,
		0x0940 => [ 0x012b ] ,
		0x0941 => [ 0x0075 ] ,
		0x0942 => [ 0x016b ] ,
		0x0943 => [ 0x1e5b ] ,
		0x0944 => [ 0x1e5d ] ,
		0x0945 => [ 0x00ea ] ,
		0x0946 => [ 0x0115 ] ,
		0x0947 => [ 0x0065 ] ,
		0x0948 => [ 0x0061 , 0x0069 ] ,
		0x0949 => [ 0x00f4 ] ,
		0x094a => [ 0x014f ] ,
		0x094b => [ 0x006f ] ,
		0x094c => [ 0x0061 , 0x0075 ] ,
		0x094d => [ ] ,
//		0x0950 => [ 0x004f , 0x004d ] ,
		0x0951 => [ ord('*') ] ,
		0x0952 => [ ord('_') ] ,
		0x0953 => [ ord('`') ] ,
		0x0954 => [ 0x00b4 ] ,

		// this is for the Bengali AU length mark which of course shouldn't actually occur
		0x0957 => [ 0x01e1 ] ,
		
		0x0958 => [ 0x0071 ] ,
		0x0959 => [ ord('x') ] ,
		0x095a => [ 0x0121 ] ,
		0x095b => [ 0x007a ] ,
		0x095c => [ 0x1e5b ] ,
		0x095d => [ 0x1e5b , 0x0068 ] ,
		0x095e => [ 0x0066 ] ,
		0x095f => [ 0x1e8f ] ,
		0x0960 => [ 0x1e5d ] ,
		0x0961 => [ 0x1e39 ] ,
		0x0962 => [ 0x1e37 ] ,
		0x0963 => [ 0x1e39 ] ,

		// actually, in lossless, leave these two as-is
//		0x0964 => [ 0x002e ] ,
//		0x0965 => [ 0x002e ] ,
		0x0966 => [ 0x0030 ] ,
		0x0967 => [ 0x0031 ] ,
		0x0968 => [ 0x0032 ] ,
		0x0969 => [ 0x0033 ] ,
		0x096a => [ 0x0034 ] ,
		0x096b => [ 0x0035 ] ,
		0x096c => [ 0x0036 ] ,
		0x096d => [ 0x0037 ] ,
		0x096e => [ 0x0038 ] ,
		0x096f => [ 0x0039 ] ,

		// left as-is for same reason as danda and double danda.
		0x0970 => [ 0x002e ] ,
		
		/* lang-specific additions? */

		/* Sinhala -- all is specific & v. rough based on Unicode standard descriptions */
		0x0d82 => ['~' ] ,
		0x0d83 => [ ord('h') ] ,
		0x0d85 => [ ord('a') ] ,
		0x0d86 => [ ord('a') , ord('a') ] ,
		0x0d87 => [ ord('a') , ord('e') ] ,
		0x0d88 => [ ord('a') , ord('a') , ord('e') ] ,
		0x0d89 => [ ord('i') ] ,
		0x0d8a => [ ord('i') , ord('i') ] ,
		0x0d8b => [ ord('u') ] ,
		0x0d8c => [ ord('u') , ord('u') ] ,
		0x0d8d => [ ord('r') ] ,
		0x0d8e => [ ord('r') , ord('r') ] ,
		0x0d8f => [ ord('l') ] ,
		0x0d90 => [ ord('l') , ord('l') ] ,
		0x0d91 => [ ord('e') ] ,
		0x0d92 => [ ord('e') , ord('e') ] ,
		0x0d93 => [ ord('a') , ord('i') ] ,
		0x0d94 => [ ord('o') ] ,
		0x0d95 => [ ord('o') , ord('o') ] ,
		0x0d96 => [ ord('a') , ord('u') ] ,

		0x0d9a => [ ord('k') ] ,
		0x0d9b => [ ord('k') , ord('h') ] ,
		0x0d9c => [ ord('g') ] ,
		0x0d9d => [ ord('g') , ord('h') ] ,
		0x0d9e => [ ord('n') , ord('g') ] ,
		0x0d9f => [ ord('n') , ord('n') , ord('g') ] ,
		0x0da0 => [ ord('c') ] ,
		0x0da1 => [ ord('c') , ord('h') ] ,
		0x0da2 => [ ord('j') ] ,
		0x0da3 => [ ord('j') , ord('h') ] ,
		0x0da4 => [ ord('n') , ord('y') ] ,
		0x0da5 => [ ord('j') , ord('n') , ord('y') ] ,
		0x0da6 => [ ord('n') , ord('y') , ord('j') ] ,
		0x0da7 => [ ord('T') ] ,
		0x0da8 => [ ord('T') , ord('h') ] ,
		0x0da9 => [ ord('D') ] ,
		0x0daa => [ ord('D') , ord('h') ] ,
		0x0dab => [ ord('N') ] ,
		0x0dac => [ ord('N') , ord('D') ] ,
		0x0dad => [ ord('t') ] ,
		0x0dae => [ ord('t') , ord('h') ] ,
		0x0daf => [ ord('d') ] ,
		0x0db0 => [ ord('d') , ord('h') ] ,
		0x0db1 => [ ord('n') ] ,

		0x0db3 => [ ord('n') , ord('d') ] ,
		0x0db4 => [ ord('p') ] ,
		0x0db5 => [ ord('p') , ord('h') ] ,
		0x0db6 => [ ord('b') ] ,
		0x0db7 => [ ord('b') , ord('h') ] ,
		0x0db8 => [ ord('m') ] ,
		0x0db9 => [ ord('m') , ord('b') ] ,
		0x0dba => [ ord('y') ] ,
		0x0dbb => [ ord('r') ] ,

		0x0dbd => [ ord('l') ] ,

		0x0dc0 => [ ord('v') ] ,
		0x0dc1 => [ ord('s') , ord('h') ] ,
		0x0dc2 => [ ord('S') ] ,
		0x0dc3 => [ ord('s') ] ,
		0x0dc4 => [ ord('h') ] ,
		0x0dc5 => [ ord('L') ] ,
		0x0dc6 => [ ord('f') ] ,

		0x0dca => [ ] ,
	
		0x0dcf => [ ord('a') , ord('a') ] ,
		0x0dd0 => [ ord('a') , ord('e') ] ,
		0x0dd1 => [ ord('a') , ord('a') , ord('e') ] ,
		0x0dd2 => [ ord('i') ] ,
		0x0dd3 => [ ord('i') , ord('i') ] ,
		0x0dd4 => [ ord('u') ] ,

		0x0dd6 => [ ord('u') , ord('u') ] ,

		0x0dd8 => [ ord('r') ] ,
		0x0dd9 => [ ord('e') ] ,
		0x0dda => [ ord('e') , ord('e') ] ,
		0x0ddb => [ ord('a') , ord('i') ] ,
		0x0ddc => [ ord('o') ] ,
		0x0ddd => [ ord('o') , ord('o') ] ,
		0x0dde => [ ord('a') , ord('u') ] ,
		0x0ddf => [ ord('l') ] ,

		0x0df2 => [ ord('r') , ord('r') ] ,
		0x0df3 => [ ord('l') , ord('l') ] ,
		0x0df4 => [ ord('.') ] ,



		}

		/* adjust indic virama */
		if (current > 0x0900 && current < 0x0e00 &&
			(glyphtype(current) == CONSONANT || current == 0x093c ) )
		{
			if (glyphtype(next) == VIRAMA ||
				( glyphtype(next) == VOWEL && next != 0x0902 && next != 0x0901) ||
				next == 0x093c)
				;
			else
				/* add an "a" to the target string */
				ustrcat(target, astr);
		}


	}





	return 0;
}










/* for ease of computation, this is a copy of Unicodify's glyphtype. */
/* Remember to update this as that algorithm changes! */

int glyphtype(unichar ch)
{
	/* Hindi / Devanagari */

	if (ch == 0x094d)
		return VIRAMA;
	if ( (ch > 0x0914 && ch < 0x093a) || (ch > 0x0957 && ch < 0x0960 ) )
		return CONSONANT;
	if ( (ch > 0x0900 && ch < 0x0904) || (ch > 0x093d && ch < 0x094d) || (ch > 0x0950 && ch < 0x0955) || (ch > 0x0961 && ch < 0x0964) )
		return VOWEL;
	if ( (ch > 0x0904 && ch < 0x0915) || (ch > 0x095f && ch < 0x0962 ) )
		return IND_VOWEL;

	/* Bengali */

	if (ch == 0x09cd)
		return VIRAMA;
	if ( (ch > 0x0994 && ch < 0x09ba) || (ch > 0x09db && ch < 0x0960) || (ch > 0x09ef && ch < 0x09f2) )
		return CONSONANT;
	if ( (ch > 0x0980 && ch < 0x0984) || (ch > 0x09bd && ch < 0x09cd) || (ch == 0x09d7) || (ch > 0x09e1 && ch < 0x09e4) )
		return VOWEL;
	if ( (ch > 0x0984 && ch < 0x0995) || (ch > 0x09df && ch < 0x09e2 ) )
		return IND_VOWEL;

	/* Punjabi */

	if (ch == 0x0a4d)
		return VIRAMA;
	if ( (ch > 0x0a14 && ch < 0x0a3a) || (ch > 0x0a58 && ch < 0x0a5f) )
		return CONSONANT;
	if ( (ch > 0x0a00 && ch < 0x0a4d) || (ch == 0x0a3c) || (ch > 0x0a6f && ch < 0x0a72) )
		return VOWEL;
	if ( (ch > 0x0a04 && ch < 0x0a15) || (ch > 0x0a71 && ch < 0x0a75) )
		return IND_VOWEL;

	/*  Gujarati */

	if (ch == 0x0acd)
		return VIRAMA;
	if (ch > 0x0a94 && ch < 0x0aba)
		return CONSONANT;
	if ( (ch > 0x0a80 && ch < 0x0a84) || (ch == 0x0abc) || (ch > 0x0abd && ch < 0x0acd) )
		return VOWEL;
	if (ch == 0x0ae0 || (ch > 0x0a84 && ch < 0x0a95) )
		return IND_VOWEL;

	/* Tamil */

	if (ch == 0x0bcd)
		return VIRAMA;
	if (ch > 0x0b94 && ch < 0x0bba)
		return CONSONANT;
	if ( (ch > 0x0b81 && ch < 0x0b84) || (ch > 0x0bbd && ch < 0x0bcd) )
		return VOWEL;
	if (ch > 0x0b84 && ch < 0x0b95)
		return IND_VOWEL;

	/* Sinhala */

	if (ch == 0x0dca)
		return VIRAMA;
	if (ch > 0x0d99 && ch < 0x0dc7)
		return CONSONANT;
	if ( (ch > 0x0d81 && ch < 0x0d84) || (ch > 0x0dce && ch < 0x0df4) )
		return VOWEL;
	if (ch > 0x0d84 && ch < 0x0d97)
		return IND_VOWEL;


	return OTHER;
}


END_OF_C;
