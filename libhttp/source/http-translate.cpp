#include "http-translate.h"
#include "cstringext.h"
#include "sys/system.h"
#include "libct/auto_ptr.h"
#include "tools.h"
#include "error.h"
#include "dxt.h"
#include "network-http.h"
#include <stdio.h>

static int http(const char* uri, const char* req, mmptr& reply)
{
	int r = -1;
	for(int i=0; r<0 && i<30; i++)
	{
		r = network_http(uri, req, reply);
		for(int j=0; ERROR_HTTP_REDIRECT==r && j<5; j++)
			r = network_http(uri, req, reply);

		if(r < 0)
		{
			printf("get %s error: %d\n", uri, r);
			system_sleep(1000);
		}
	}
	return r;
}

int http_translate(const char* uri, const char* req, const char* xml, OnTranslated callback, void* param)
{
	mmptr reply;
	int r = http(uri, req, reply);
	if(r < 0)
	{
		printf("http_translate [get]%s: %d\n", uri, r);
		return r;
	}

	libct::auto_ptr<char> result;
	r = DxTransformHtml(&result, reply, xml);
	if(r < 0)
	{
		//tools_write("e:\\a.html", reply, strlen(reply));
		printf("http_translate [dxt]%s: %d\n", uri, r);
		return r;
	}

	return callback ? callback(param, result) : ERROR_PARAM;
}
