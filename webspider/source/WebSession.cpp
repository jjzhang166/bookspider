#include "WebSession.h"
#include "cstringext.h"
#include "sys/path.h"
#include "http-server.h"
#include "error.h"
#include "dlog.h"
#include "url.h"
#include "config.h"
#include "WebProcess.h"
#include <time.h>

#define SERVER_TIMEOUT	(5*60*1000)

WebSession::WebSession(socket_t sock, const char* ip, int port)
{
	m_ip = ip;
	m_sock = sock;
	m_port = port;

	m_http = http_server_create(sock);
	dlog_log("new client[%s.%d] connected.\n", ip, port);
}

WebSession::~WebSession()
{
	http_server_destroy(&m_http);
	dlog_log("client[%s.%d] disconnected.\n", m_ip.c_str(), m_port);
}

void WebSession::Run(void* param)
{
	WebSession* session = (WebSession*)param;
	session->Run();
}

void WebSession::Run()
{
	int r = 0;
	assert(socket_invalid != m_sock);
	while(1)
	{
		r = Recv();
		if(r < 0)
			break; // exit

		OnApi();
	}

	dlog_log("\n[%d] client %s.%d disconnect[%d].\n", (int)time(NULL), m_ip.c_str(), m_port, r);
	delete this;
}

void WebSession::OnApi()
{
	typedef int (WebSession::*Handler)(jsonobject& reply);
	typedef std::map<std::string, Handler> THandlers;
	static THandlers handlers;
	if(0 == handlers.size())
	{
		handlers.insert(std::make_pair("library", &WebSession::OnBookLibrary));
		handlers.insert(std::make_pair("top", &WebSession::OnBookTop));
		handlers.insert(std::make_pair("list", &WebSession::OnBookList));
		handlers.insert(std::make_pair("update", &WebSession::OnBookUpdate));
	}

	std::string json;
	if(0 == strncmp(m_path.c_str(), "/api/", 5))
	{
		THandlers::iterator it;
		it = handlers.find(m_path.substr(5));
		if(it != handlers.end())
		{
			jsonobject reply;
			(this->*(it->second))(reply);
			json = reply.json();
		}
	}

	if(json.empty())
	{
		jsonobject reply;
		reply.add("code", -1).add("msg", "command not found");
		std::string json = reply.json();
	}

	Send(200, "application/json", json.c_str(), json.length());
}

int WebSession::OnBookLibrary(jsonobject& reply)
{
	process_t pid = 0;
	int r = web_process_create("debug/book-library.exe", "--library", "", &pid);
	if(r < 0)
	{
		reply.add("code", r).add("msg", "start process error.");
	}
	else
	{
		reply.add("code", 0).add("msg", "ok");
	}
	return r;
}

int WebSession::OnBookTop(jsonobject& reply)
{
	process_t pid = 0;
	int r = web_process_create("book-library.exe", "--top", "", &pid);
	if(r < 0)
	{
		reply.add("code", r).add("msg", "start process error.");
	}
	else
	{
		reply.add("code", 0).add("msg", "ok");
	}
	return r;
}

int WebSession::OnBookList(jsonobject& reply)
{
	std::string booksite;
	std::map<std::string, std::string>::const_iterator it;
	it = m_params.find("site");
	if(it == m_params.end())
	{
		reply.add("code", -1).add("msg", "start process error.");
		return -1;
	}

	booksite = it->second;

	process_t pid = 0;
	int r = web_process_create("book-spider.exe", "--list", booksite.c_str(), &pid);
	if(r < 0)
	{
		reply.add("code", r).add("msg", "start process error.");
	}
	else
	{
		reply.add("code", 0).add("msg", "ok");
	}
	return r;
}

int WebSession::OnBookUpdate(jsonobject& reply)
{
	std::string booksite;
	std::map<std::string, std::string>::const_iterator it;
	it = m_params.find("site");
	if(it == m_params.end())
	{
		reply.add("code", -1).add("msg", "start process error.");
		return -1;
	}

	booksite = it->second;

	process_t pid = 0;
	int r = web_process_create("book-spider.exe", "--update", booksite.c_str(), &pid);
	if(r < 0)
	{
		reply.add("code", r).add("msg", "start process error.");
	}
	else
	{
		reply.add("code", 0).add("msg", "ok");
	}
	return r;
}

int WebSession::Recv()
{
	m_content = NULL;
	m_contentLength = 0;

	int r = socket_select_read(m_sock, SERVER_TIMEOUT);
	if(r <= 0)
		return 0==r ? ERROR_RECV_TIMEOUT : r;

	r = http_server_recv(m_http);
	if(r < 0)
		return r;

	void* url = url_parse(http_server_get_path(m_http));
	for(int i=0; i<url_getparam_count(url); i++)
	{
		const char *name, *value;
		if(0 != url_getparam(url, i, &name, &value))
			continue;
		m_params.insert(std::make_pair(std::string(name), std::string(value)));
	}
	m_path.assign(url_getpath(url));
	url_free(url);

	http_server_get_content(m_http, &m_content, &m_contentLength);
	if(m_contentLength > 0 && m_contentLength < 2*1024)
	{
		printf("%s\n", (const char*)m_content);
	}
	return 0;
}

int WebSession::Send(int code, const char* contentType, const void* data, int len)
{
	http_server_set_header(m_http, "Server", "MD WebServer 0.1");
	http_server_set_header(m_http, "Connection", "keep-alive");
	http_server_set_header(m_http, "Keep-Alive", "timeout=5,max=100");
	http_server_set_header(m_http, "Content-Type", contentType);
	//http_server_set_header(m_http, "Content-Type", "text/html; charset=utf-8");
	http_server_set_header_int(m_http, "Content-Length", len);

	int r = http_server_send(m_http, code, (void*)data, len);

	if(len > 0 && len < 2*1024)
	{
		dlog_log("%s\n", (const char*)data);
	}
	return r;
}
