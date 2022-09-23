package solr

import (
	"context"
	"fmt"
	"github.com/PuerkitoBio/goquery"
	"github.com/nakamura-akifumi/kassis/internal/fileutils"
	"github.com/rs/zerolog/log"
	"io"
	"io/ioutil"
	"net/http"
	"net/url"
	"path/filepath"
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

	conn := &Connection{Uri: uri, httpClient: client}
	bp := conn.formatBasePath()
	return &AdminClient{conn: conn, BasePath: bp}, nil
}

func (ca *AdminClient) Ping() (string, string, error) {
	solrhome := ""
	specversion := ""

	uri := ca.BasePath + "/admin/info/system?wt=xml"
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

func (ca *AdminClient) CopyConfigsetFromDefault(newcorename string) error {
	fmt.Printf("copy solr configset\n")

	_, sh, err := ca.Ping()
	if err != nil {
		return err
	}

	defaultsolrconfigset := filepath.Join(sh, "configsets", "_default")
	destdir := filepath.Join(sh, newcorename)

	log.Debug().Msgf("copy %s to %s", defaultsolrconfigset, destdir)

	err = fileutils.CopyDirectory(defaultsolrconfigset, destdir)
	if err != nil {
		fmt.Printf("%s", err)
		log.Err(err)
		return err
	}

	return nil
}

func (ca *AdminClient) FindCoreByName(corename string) (*CoreStatusResponse, error) {
	params := &url.Values{}
	res, err := ca.Action("STATUS", params)
	if err != nil {
		return nil, err
	}

	var r *CoreStatusResponse
	for _, x := range res.Status {
		if x.Name == corename {
			r = x
		}
	}
	return r, nil
}

func (ca *AdminClient) Get(params *url.Values) (*AdminResponse, error) {
	ctx := context.Background()

	params.Set("wt", "json")
	uri := fmt.Sprintf("%s/admin/cores?%s", ca.BasePath, params.Encode())
	res, err := ca.conn.adminRequest(ctx, http.MethodGet, uri, nil)
	if err != nil {
		return nil, err
	}
	return res, nil
}

func (ca *AdminClient) ForceUnload(corename string) (*AdminResponse, error) {
	params := &url.Values{}
	params.Add("core", corename)
	params.Add("deleteIndex", "true")
	params.Add("deleteDataDir", "true")
	params.Add("deleteInstanceDir", "true")
	return ca.Action("UNLOAD", params)
}

func (ca *AdminClient) Action(action string, params *url.Values) (*AdminResponse, error) {
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

func (ca *AdminClient) UpdateSolrSchema(corename string, schemafilename string) error {
	bytes, err := ioutil.ReadFile(schemafilename)
	if err != nil {
		log.Err(err)
		return err
	}

	uri := ca.BasePath + "/" + corename + "/schema"
	req, err := http.NewRequest(http.MethodPost, uri, strings.NewReader(string(bytes)))
	if err != nil {
		log.Error().Msgf("request[1] to %s", uri)
		log.Err(err)
		return err
	}
	req.Header.Set("Content-Type", "Content-type:application/json")

	client := &http.Client{}
	resp, err := client.Do(req)
	if err != nil {
		log.Error().Msgf("request[2] to %s", uri)
		log.Err(err)
		return err
	}
	// deferでクローズ処理
	defer func(Body io.ReadCloser) {
		err := Body.Close()
		if err != nil {

		}
	}(resp.Body)
	// Bodyの内容を読み込む
	body, _ := io.ReadAll(resp.Body)

	if resp.StatusCode != 200 {
		log.Error().Msgf("error: response code is %d. uri is %s", resp.StatusCode, uri)
		fmt.Println("Error:")
		fmt.Print(string(body))
		return fmt.Errorf("error: response code is %d", resp.StatusCode)
	}

	fmt.Println("success")
	return nil
}
