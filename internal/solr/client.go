package solr

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"github.com/rs/zerolog/log"
	"net/http"
	"net/url"
	"runtime"
	"strconv"
)

type Connection struct {
	httpClient *http.Client
	Uri        string
	Corename   string
}

type WriteOptions struct {
	Commit         bool
	CommitWithin   int64
	AllowDuplicate bool
}

func (opts *WriteOptions) buildQueryOptions() url.Values {
	if opts == nil {
		return nil
	}

	q := make(url.Values)
	if opts.Commit {
		q.Set("commit", "true")
	}
	if opts.CommitWithin > 0 {
		q.Set("commitWithin", strconv.FormatInt(opts.CommitWithin, 10))
	}
	if opts.AllowDuplicate {
		q.Set("overwrite", "false")
	}
	return q
}

func (c *Connection) formatBasePath() string {
	return formatBasePath(c.Uri, c.Corename)
}

func (c *Connection) adminRequest(ctx context.Context, method, uri string, body []byte) (*AdminResponse, error) {
	req, err := http.NewRequest(method, uri, bytes.NewBuffer(body))
	if err != nil {
		return nil, err
	}

	req.Header.Add("Content-Type", "application/json")
	req.Header.Set("User-Agent", fmt.Sprintf("kassis internal %s", runtime.GOOS))

	res, err := c.httpClient.Do(req.WithContext(ctx))
	if err != nil {
		log.Err(err).Msg("httpclient request failed.")
		return nil, err
	}

	var r AdminResponse
	defer res.Body.Close()

	err = json.NewDecoder(res.Body).Decode(&r)
	if err != nil {
		log.Err(err).Msg("response decoder convert error.")
		return nil, err
	}

	if r.Error != nil {
		return &r, r.Error
	}

	return &r, nil
}

func (c *Connection) request(ctx context.Context, method, uri string, body []byte) (*Response, error) {

	//log.Debug().Msgf("uri:%s", uri)

	req, err := http.NewRequest(method, uri, bytes.NewBuffer(body))
	if err != nil {
		return nil, err
	}

	req.Header.Add("Content-Type", "application/json")
	req.Header.Set("User-Agent", fmt.Sprintf("kassis internal %s", runtime.GOOS))

	res, err := c.httpClient.Do(req.WithContext(ctx))
	if err != nil {
		log.Err(err).Msg("httpclient request failed.")
		return nil, err
	}

	var r Response
	defer res.Body.Close()

	err = json.NewDecoder(res.Body).Decode(&r)
	if err != nil {
		log.Err(err).Msg("response decoder convert error.")
		return nil, err
	}

	if r.Error != nil {
		return &r, r.Error
	}

	return &r, nil
}

type Command map[string]interface{}

type SingleClient struct {
	conn     *Connection
	BasePath string
	cmds     []Command
}

func (c *SingleClient) buildURL(path string, query string) string {
	if query != "" {
		return c.BasePath + path + "?" + query
	}
	return c.BasePath + path
}

func (c *SingleClient) NewCommandBuilder() {
}
func NewConnectionAndSingleClient(uri, corename string, client *http.Client) (*SingleClient, error) {
	if uri == "" || corename == "" {
		return nil, fmt.Errorf("invalid parameter (uri or corename is empty)")
	}
	_, err := url.ParseRequestURI(uri)
	if err != nil {
		return nil, err
	}

	conn := &Connection{Uri: uri, Corename: corename, httpClient: client}

	bp := conn.formatBasePath()
	return &SingleClient{conn: conn, BasePath: bp}, nil
}

func (c *SingleClient) Ping(ctx context.Context) (string, int32, error) {
	uri := c.buildURL("/admin/ping", "")
	res, err := c.conn.request(ctx, http.MethodGet, uri, nil)
	if err != nil {
		return "", -1, err
	}

	if res.Status != nil && *res.Status != "OK" {
		return "NG", -1, fmt.Errorf("error pinging solr, status: %s", *res.Status)
	}

	return *res.Status, res.Header.QTime, nil
}

func (c *SingleClient) Search(ctx context.Context, q *Query) (*Response, error) {
	uri := c.buildURL("/select", q.String())
	return c.read(ctx, c, uri)
}

func (c *SingleClient) read(ctx context.Context, sc *SingleClient, url string) (*Response, error) {
	return sc.conn.request(ctx, http.MethodGet, url, nil)
}

func (c *SingleClient) Create(ctx context.Context, doc interface{}, opts *WriteOptions) (*Response, error) {
	uri := c.buildURL("/update/json/docs", opts.buildQueryOptions().Encode())

	bodyBytes, err := interfaceToBytes(doc)
	if err != nil {
		return nil, err
	}

	return c.conn.request(ctx, http.MethodPost, uri, bodyBytes)
}

func (c *SingleClient) BatchCreate(ctx context.Context, docs interface{}, opts *WriteOptions) (*Response, error) {
	uri := c.buildURL("/update/json/docs", opts.buildQueryOptions().Encode())

	bodyBytes, err := interfaceToBytes(docs)
	if err != nil {
		return nil, err
	}

	return c.conn.request(ctx, http.MethodPost, uri, bodyBytes)
}

func (c *SingleClient) Add(ctx context.Context, doc Doc, opts *WriteOptions) (*Response, error) {
	uri := c.buildURL("/update", opts.buildQueryOptions().Encode())
	//TODO: check response status
	return c.add(ctx, uri, doc)
}

func (c *SingleClient) add(ctx context.Context, url string, doc Doc) (*Response, error) {
	cmd := map[string]Doc{"add": doc}
	bodyBytes, err := interfaceToBytes(cmd)
	if err != nil {
		return nil, err
	}
	return c.conn.request(ctx, http.MethodPost, url, bodyBytes)
}

func (c *SingleClient) delete(ctx context.Context, url string, doc Doc) (*Response, error) {
	cmd := map[string]Doc{"delete": doc}
	bodyBytes, err := interfaceToBytes(cmd)
	if err != nil {
		return nil, err
	}
	return c.conn.request(ctx, http.MethodPost, url, bodyBytes)
}

func (c *SingleClient) DeleteByQuery(ctx context.Context, query string, opts *WriteOptions) (*Response, error) {
	uri := c.buildURL("/update", opts.buildQueryOptions().Encode())
	return c.delete(ctx, uri, Doc{"query": query})
}

func (c *SingleClient) DeleteAll(ctx context.Context) (interface{}, error) {
	return c.DeleteByQuery(ctx, "*:*", &WriteOptions{Commit: true})
}

func (c *SingleClient) commit(ctx context.Context, url string) (*Response, error) {
	cmd := map[string]Doc{"commit": {}}
	bodyBytes, err := interfaceToBytes(cmd)
	if err != nil {
		return nil, err
	}
	return c.conn.request(ctx, http.MethodPost, url, bodyBytes)
}

func (c *SingleClient) Commit(ctx context.Context) (*Response, error) {
	uri := c.BasePath + "/update"
	return c.commit(ctx, uri)
}
