#ifndef _booksite_h_
#define _booksite_h_

#include "dllexport.h"

#ifdef BOOKSPIDER_EXPORTS
	#define BOOKSITE_API DLL_EXPORT_API
#else
	#define BOOKSITE_API DLL_IMPORT_API
#endif

#ifdef  __cplusplus
extern "C" {
#endif

// top type
enum ETopType{ 
	ETT_ALL_VIEW=1, // all page view
	ETT_ALL_MARK,	// all bookmark
	ETT_ALL_VOTE,	// all user vote
	ETT_MONTH_VIEW,	// month
	ETT_MONTH_MARK,
	ETT_MONTH_VOTE,
	ETT_WEEK_VIEW,	// week
	ETT_WEEK_MARK,
	ETT_WEEK_VOTE,
};

typedef struct 
{
	char name[100];		// book name
	char author[100];	// author name
	char uri[128];		// http://www.book.com/books/book.html
	char category[20];	// magic
	char chapter[100];	// book chapter
	char datetime[24];	// 2012-08-21 10:11:32
	int siteid;
	int vote;
} book_t;

typedef int (*book_site_spider_fcb)(void* param, const book_t* book);

typedef struct 
{
	int id;
	const char* name;
	int (*spider)(int top, book_site_spider_fcb callback, void* param);
} book_site_t;

BOOKSITE_API int book_site_register(const book_site_t* site);

BOOKSITE_API int book_site_count();

BOOKSITE_API const book_site_t* book_site_get(int index);

#ifdef  __cplusplus
}  // extern "C"
#endif

#endif /* !_booksite_h_ */
