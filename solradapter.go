package kassiscore

import (
	"fmt"
	"github.com/PuerkitoBio/goquery"
	"github.com/google/uuid"
	"github.com/rs/zerolog/log"
	"github.com/vanng822/go-solr/solr"
	"io"
	"io/ioutil"
	"net/http"
	"net/url"
	"strings"
)

func SolrClearDocument(uriaddress string, corename string) error {

	uri := uriaddress + "/solr"
	si, err := solr.NewSolrInterface(uri, corename)
	if err != nil {
		log.Fatal().
			Err(err).
			Msgf("Connection error.")
		return err
	}

	_, err = si.DeleteAll()
	if err != nil {
		log.Fatal().Err(err)
		return err
	}

	return nil
}

func SolrAddDocument(si *solr.SolrInterface, materialid string, objecttype string, contents []string, vparams map[string]string) error {

	uuidObj, _ := uuid.NewUUID()
	id := uuidObj.String()

	var errChuck error
	var sdocs []solr.Document

	sdoc := solr.Document{
		"id":         id,
		"materialid": materialid,
		"objecttype": objecttype,
		"contents":   contents,
	}

	for k, v := range vparams {
		sdoc.Set(k, v)
	}

	sdocs = append(sdocs, sdoc)
	sparams := &url.Values{}
	sparams.Add("commitWithin", "1000")
	sparams.Add("overwrite", "true")

	//fmt.Printf("%+v\n", doc)
	//fmt.Println("try create to solr")
	res, err := si.Add(sdocs, 0, sparams)
	if err != nil {
		log.Fatal().Err(err)
		return err
	}
	if len(res.Result) == 0 || len(res.Result) != len(sdocs) {
		log.Error().Msg("chunk size unmatched")
	} else {
		for _, v := range res.Result {
			v2 := v.(solr.M)
			//fmt.Printf("%+v\n", v2)
			if v2["success"] == false {
				v3 := v2["result"].(map[string]interface{})
				msg := v3["error"].(map[string]interface{})["msg"]
				fmt.Println(msg)
				errChuck = fmt.Errorf("error: %s", msg)
			}
		}
	}
	return errChuck
}

func SolrSchemaUpdate(uri string, corename string, schemafilename string) error {
	bytes, err := ioutil.ReadFile(schemafilename)
	if err != nil {
		log.Err(err)
		return err
	}

	uri = uri + "/solr/" + corename + "/schema"
	req, err := http.NewRequest("POST", uri, strings.NewReader(string(bytes)))
	if err != nil {
		log.Err(err)
		return err
	}
	req.Header.Set("Content-Type", "Content-type:application/json")

	fmt.Println("Post schema file")
	client := &http.Client{}
	resp, err := client.Do(req)
	if err != nil {
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
		log.Error().Msgf("error: response code is %d", resp.StatusCode)
		fmt.Println("Error:")
		fmt.Print(string(body))
		return fmt.Errorf("error: response code is %d", resp.StatusCode)
	}

	fmt.Println("success")
	return nil
}

func SolrServerPing(uri string) (string, error) {
	var vs string
	var sh string

	uri = uri + "/solr/admin/info/system?wt=xml"
	resp, err := http.Get(uri)
	if err != nil {
		//fmt.Printf("error: %s\n", err.Error())
		return "", err
	} else {
		defer func(Body io.ReadCloser) {
			err := Body.Close()
			if err != nil {
				return
			}
		}(resp.Body)
		if resp.StatusCode != 200 {
			log.Error().Msgf("error: response code is %d", resp.StatusCode)
			return "", fmt.Errorf("error: response code is %d", resp.StatusCode)
		}

		doc, err := goquery.NewDocumentFromReader(resp.Body)
		if err != nil {
			log.Fatal().Err(err)
			return "", err
		}
		vs = doc.Find("[name=\"solr-spec-version\"]").Text()
		sh = doc.Find("[name=\"solr_home\"]").Text()

		log.Debug().Msgf("solr_home:%s\n", sh)
		//fmt.Printf("solr-spec-version:%s\n", vs)
	}

	return vs, nil
}
