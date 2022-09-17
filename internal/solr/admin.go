package solr

import (
	"context"
	"fmt"
	"github.com/PuerkitoBio/goquery"
	"io"
	"net/http"
	"net/url"
	"strings"
)

type AdminClient struct {
	conn     *Connection
	BasePath string
}

func NewConnectionAndAdminClient(uri string, client *http.Client) (*AdminClient, error) {
	if uri == "" {
		return nil, fmt.Errorf("invalid parameter (uri is empty)")
	}
	_, err := url.ParseRequestURI(uri)
	if err != nil {
		return nil, err
	}
	uri = uri + "/solr/"

	conn := &Connection{Uri: uri, httpClient: client}
	bp := conn.formatBasePath()
	return &AdminClient{conn: conn, BasePath: bp}, nil
}

func (ca *AdminClient) Get(params *url.Values) (*Response, error) {
	ctx := context.Background()

	params.Set("wt", "json")
	uri := fmt.Sprintf("%s/admin/cores?%s", ca.BasePath, params.Encode())
	res, err := ca.conn.request(ctx, http.MethodGet, uri, nil)
	if err != nil {
		return nil, err
	}
	return res, nil
}

func (ca *AdminClient) Action(action string, params *url.Values) (*Response, error) {
	switch strings.ToUpper(action) {
	case "STATUS":
		params.Set("action", "STATUS")
	case "RELOAD":
		params.Set("action", "RELOAD")
	case "CREATE":
		params.Set("action", "CREATE")
	case "RENAME":
		params.Set("action", "RENAME")
	case "SWAP":
		params.Set("action", "SWAP")
	case "UNLOAD":
		params.Set("action", "UNLOAD")
	case "SPLIT":
		params.Set("action", "SPLIT")
	default:
		return nil, fmt.Errorf("action '%s' not supported", action)
	}
	return ca.Get(params)
}

func AdminPing(uri string) (string, string, error) {
	if uri == "" {
		return "", "", fmt.Errorf("invalid parameter (uri is empty)")
	}
	_, err := url.ParseRequestURI(uri)
	if err != nil {
		return "", "", err
	}

	solrhome := ""
	specversion := ""

	uri = uri + "/solr/admin/info/system?wt=xml"
	resp, err := http.Get(uri)
	if err != nil {
		//fmt.Printf("error: %s\n", err.Error())
		return "", "", err
	} else {
		defer func(Body io.ReadCloser) {
			err := Body.Close()
			if err != nil {
				return
			}
		}(resp.Body)
		if resp.StatusCode != 200 {
			return "", "", fmt.Errorf("error: response code is %d", resp.StatusCode)
		}

		doc, err := goquery.NewDocumentFromReader(resp.Body)
		if err != nil {
			return "", "", err
		}
		specversion = doc.Find("[name=\"solr-spec-version\"]").Text()
		solrhome = doc.Find("[name=\"solr_home\"]").Text()
	}

	return specversion, solrhome, nil
}
