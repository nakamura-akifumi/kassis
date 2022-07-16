package kassiscore

import (
	"bytes"
	"context"
	"errors"
	"fmt"
	"github.com/PuerkitoBio/goquery"
	"github.com/google/go-tika/tika"
	"github.com/rs/zerolog/log"
	"github.com/vanng822/go-solr/solr"
	"io"
	"io/ioutil"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"strings"
)

type Material struct {
	ID         string   `json:"id"`
	ObjectType string   `json:"objecttype"`
	Foldername string   `json:"foldername"`
	Filename   string   `json:"filename"`
	Sheetname  string   `json:"sheetname"`
	Mediatype  string   `json:"mediatype"`
	Contents   []string `json:"contents"`
	Title      string   `json:"title"`
}

// KWQIF は、Web用のレスポンス構造体
type KWQIF struct {
	QueryString  string
	QueryMessage string
	NumOfPage    int
	Curretpage   int
	Lastpage     int
}
type KWRIF struct {
	NumFound        int
	ResponseStatus  string
	ResponseMessage string
	KQ              KWQIF
	Materials       []Material
}

const ContenttypeExcel string = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
const ContenttypePdf string = "application/pdf"
const ContenttypeText string = "text/plain"
const ContenttypeWord string = "application/vnd.openxmlformats-officedocument.wordprocessingml.document"

//配列の中に特定の文字列が含まれるかを返す
func arrayContains(arr []string, str string) bool {
	for _, v := range arr {
		if v == str {
			return true
		}
	}
	return false
}

func ExtnameToMediaType(extname string) string {
	ct := "application/octet-stream"
	switch extname {
	case ".xlsx":
		ct = ContenttypeExcel
	case ".pdf":
		ct = ContenttypePdf
	case ".txt":
		ct = ContenttypeText
	case ".docx":
		ct = ContenttypeWord
	}
	return ct
}

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

// SolrQuery は、Solrに検索を投げます
// TODO: 引数要改良 、solrに接続する箇所も改良。毎回接続するのは問題。
func SolrQuery(uriaddress string, corename string, qs string) (*solr.SolrResult, error) {
	uri := uriaddress + "/solr"
	si, err := solr.NewSolrInterface(uri, corename)
	if err != nil {
		log.Fatal().
			Err(err).
			Msgf("Connection error.")
		return nil, err
	}

	//opts := &solr.ReadOptions{Rows: 20, Debug: solr.DebugTypeQuery}
	q := solr.NewQuery()

	// cut whitespace and zenkaku space
	qs = strings.TrimRight(qs, " 　")
	if qs == "" {
		q.Q("*:*")
	} else {
		q.Q("contents:" + qs)
	}

	q.Start(0)
	q.Rows(20)
	/*
		q.SetParam("hl", "true")
		q.SetParam("hl.fl", "title")
		q.SetParam("hl.simple.pre", "<em>")
		q.SetParam("hl.simple.post", "</em>")
	*/
	s := si.Search(q)
	res, err := s.Result(nil)
	if err != nil {
		log.Err(err).Msgf("fail query: q=%s", q)
		return nil, err
	}

	log.Info().Msgf("NumFound/FetchDocs/Highlighting:%d/%d/%d\n", res.Results.NumFound, len(res.Results.Docs), len(res.Highlighting))

	return res, nil
}

// GenerateTextIndex は、テキスト形式のファイルの索引を作成します
// 1ファイルで Solr の1ドキュメントとする
func GenerateTextIndex(si *solr.SolrInterface, filename string, mediatype string, doc *goquery.Document) string {

	var cells []string

	// Find the review items
	doc.Find("p").Each(func(rindex int, pselection *goquery.Selection) {
		// For each item found
		text := strings.ReplaceAll(pselection.Text(), "&#13;", " ")
		text = strings.ReplaceAll(text, " ", "")

		//for debug
		//fmt.Println(text)

		cells = append(cells, text)
	})

	basename := filepath.Base(filename)
	foldername := filepath.Dir(filename)

	mid := fmt.Sprintf("%s%d", filename, 0)

	vparams := map[string]string{
		"mediatype":  mediatype,
		"foldername": foldername,
		"filename":   basename,
		"title":      basename,
	}

	err := SolrAddDocument(si, mid, "FILE", cells, vparams)
	if err != nil {
		log.Fatal().Err(err)
	}
	fmt.Print(".")

	//fmt.Println(res.Header)

	return "ok"
}

// GenerateWordxIndex は MS WORD(.docx)形式のファイルの索引を作る
// 1ファイルで Solr の1ドキュメントとする
func GenerateWordxIndex(si *solr.SolrInterface, filename string, mediatype string, doc *goquery.Document) string {

	//fmt.Println("docx")

	var cells []string

	basename := filepath.Base(filename)
	foldername := filepath.Dir(filename)

	title := basename

	doc.Find("meta").Each(func(i int, s *goquery.Selection) {
		if name, _ := s.Attr("name"); name == "dc:title" {
			c, _ := s.Attr("content")
			if c != "" {
				title = c
			}
		}
	})

	// Find the review items
	doc.Find("p").Each(func(rindex int, pageselection *goquery.Selection) {
		// For each item found
		text := strings.Replace(pageselection.Text(), "\n", " ", -1)
		text = strings.Replace(text, " ", "", -1)
		//text = rep_space.ReplaceAllString(text, "")

		//for debug
		//fmt.Println(text)

		cells = append(cells, text)
	})

	//fmt.Printf("title:%s\n", title)

	mid := fmt.Sprintf("%s%d", filename, 0)
	//wr := Material{ID: id, ObjectType: "FILE", Mediatype: mediatype, Foldername: foldername, Filename: basename, Title: title, Contents: cells}

	vparams := map[string]string{
		"mediatype":  mediatype,
		"foldername": foldername,
		"filename":   basename,
		"title":      title,
	}

	err := SolrAddDocument(si, mid, "FILE", cells, vparams)
	if err != nil {
		log.Fatal().Err(err)
	}
	fmt.Print(".")

	return "ok"
}

// GeneratePdfIndex は、PDF(.pdf)形式のファイルの索引を作る
// PDFの1ページで Solr の1ドキュメントとする
func GeneratePdfIndex(si *solr.SolrInterface, filename string, mediatype string, doc *goquery.Document) string {

	basename := filepath.Base(filename)
	foldername := filepath.Dir(filename)

	title := basename

	// Find the review items
	doc.Find("div.page").Each(func(rindex int, pageselection *goquery.Selection) {
		// For each item found
		text := strings.Replace(pageselection.Text(), "\n", " ", -1)
		text = strings.Replace(text, " ", "", -1)
		//text = rep_space.ReplaceAllString(text, "")

		mid := fmt.Sprintf("%s%d", filename, rindex)
		//doc := Material{ID: id, ObjectType: "FILE", Mediatype: mediatype, Foldername: foldername, Filename: basename, Title: title, Contents: cells}
		vparams := map[string]string{
			"mediatype":  mediatype,
			"foldername": foldername,
			"filename":   basename,
			"title":      title,
		}

		err := SolrAddDocument(si, mid, "FILE", []string{text}, vparams)
		if err != nil {
			log.Fatal().Err(err)
		}
		fmt.Print(".")
	})

	return "ok"
}

// GenerateExcelIndex は Excel(.xlsx)形式のファイルの索引を作る
// Excelの1行で Solr の1ドキュメントとする
func GenerateExcelIndex(si *solr.SolrInterface, filename string, mediatype string, doc *goquery.Document) string {
	//TODO:対象外とするシート名の受け渡しは要改良
	excludesheetnames := []string{"注意書き"}

	basename := filepath.Base(filename)
	foldername := filepath.Dir(filename)

	title := basename
	doc.Find("meta").Each(func(i int, s *goquery.Selection) {
		if name, _ := s.Attr("name"); name == "dc:title" {
			c, _ := s.Attr("content")
			if c != "" {
				title = c
			}
		}
	})

	doc.Find("body div").Each(func(rindex int, sheetselection *goquery.Selection) {
		sheetname := sheetselection.Find("h1").Text()

		if !arrayContains(excludesheetnames, sheetname) {

			// Find the review items
			sheetselection.Find("table tbody tr").Each(func(rindex int, rowselection *goquery.Selection) {
				// For each item found
				var cells []string

				innerselection := rowselection.Find("td")
				innerselection.Each(func(cindex int, cellsel *goquery.Selection) {
					//fmt.Printf("cell %d %d: %s\n", rindex, cindex, cellsel.Text())

					cells = append(cells, cellsel.Text())
				})

				// 情報がある行のみ索引を作成する（空行は索引に含めない）
				if len(cells) > 0 {
					mid := fmt.Sprintf("%s%s%d", filename, sheetname, rindex)

					vparams := map[string]string{
						"mediatype":  mediatype,
						"foldername": foldername,
						"filename":   basename,
						"sheetname":  sheetname,
						"title":      title,
					}

					err := SolrAddDocument(si, mid, "FILE", cells, vparams)
					if err != nil {
						log.Fatal().Err(err)
						//return err
					}
					fmt.Print(".")
				}
			})
		}

	})

	return "ok"
}

func ImportFromFile(files []string, tikaserveruri string, solrserveruri string, solrcorename string) error {

	uri := solrserveruri + "/solr"
	si, err := solr.NewSolrInterface(uri, solrcorename)
	if err != nil {
		log.Fatal().Err(err)
		return err
	}
	status, qtime, err := si.Ping()
	if err != nil {
		fmt.Printf("Solr core ping:ng (%s %s)\n", solrserveruri, solrcorename)
		return err
	}
	log.Debug().Msgf("Solr Ping status:%s qtime:%d\n", status, qtime)

	//Create connection with tika server
	tikaclient := tika.NewClient(nil, tikaserveruri)

	successCount := 0
	for _, filename := range files {
		fmt.Printf("%d/%d filename:%s\n", successCount+1, len(files), filename)
		//Get the file and open it
		file, err := os.Open(filename)
		if err != nil {
			return errors.New(fmt.Sprintf("os: Unable to open file [%s]", filename))
		}

		//TODO: close file
		//defer file.Close()

		extname := filepath.Ext(filename)
		contentType := ExtnameToMediaType(extname)
		header := http.Header{}
		header.Add("Content-Type", contentType)
		//Read the content from file
		body, err := tikaclient.ParseWithHeader(context.Background(), file, header)
		if err != nil {
			//log.Fatal().Err(err)
			if err.Error() == "response code 422" {
				// for empty file (0byte stream)
				continue
			}
			if strings.Contains(err.Error(), "connectex: No connection could be made because the target machine actively refused it.") {
				// cannot connect to tike server
				return err
			}
			fmt.Println("err?:")
			fmt.Println(err.Error())
		}

		// Load the HTML document
		doc, err := goquery.NewDocumentFromReader(strings.NewReader(body))
		if err != nil {
			log.Fatal().Err(err)
		}

		//for debug
		//fmt.Print("@1")
		//fmt.Print(body)

		//TODO: 拡張子とフォーマットのMAPから選択したい
		switch extname {
		case ".xlsx":
			GenerateExcelIndex(si, filename, contentType, doc)
		case ".pdf":
			GeneratePdfIndex(si, filename, contentType, doc)
		case ".docx":
			GenerateWordxIndex(si, filename, contentType, doc)
		case ".txt":
			GenerateTextIndex(si, filename, contentType, doc)
		default:
			fmt.Printf("\nunknown format: skip [%s]\n", filename)
		}
		fmt.Printf("\n")
		successCount++
	}

	if si != nil {
		_, err := si.Commit()
		if err != nil {
			fmt.Println(err)
		}
	}

	fmt.Printf("success_count=%d\n", successCount)

	return nil
}

// 内部関数
func netFetch(uri string, filename string, cfg *KENVCONF) (*bytes.Buffer, error) {
	bytebuf := new(bytes.Buffer)

	// Getリクエスト
	res, err := http.Get(uri)
	if err != nil {
		log.Err(err).Msg("error occurred")
		return bytebuf, err
	}

	defer func(Body io.ReadCloser) {
		err := Body.Close()
		if err != nil {

		}
	}(res.Body)

	log.Debug().Msgf("%s", res.Header)

	// 読み取り
	buf, _ := ioutil.ReadAll(res.Body)

	bReader := bytes.NewReader(buf)

	io.Copy(bytebuf, bReader)
	ioutil.WriteFile(filename, bytebuf.Bytes(), os.ModePerm)

	return bytebuf, nil
}

func WebCrawler(uri string, cfg *KENVCONF) (string, error) {

	foldername := "foo"
	str1 := url.PathEscape(uri)
	filename := filepath.Join(cfg.ExtDir, foldername, str1)

	log.Debug().Str("filename", filename)

	byteBuf, err := netFetch(uri, filename, cfg)
	if err != nil {
		return "ng", err
	}

	// HTMLパース
	//fmt.Println(bytebuf)
	doc, _ := goquery.NewDocumentFromReader(byteBuf)

	doc.Find("a").Each(func(_ int, s *goquery.Selection) {
		uri, _ := s.Attr("href")
		fmt.Println(uri)
	})

	return "ok", nil
}
