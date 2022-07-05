package kassiscore

import (
	"bufio"
	"bytes"
	"context"
	"errors"
	"fmt"
	"github.com/rs/zerolog/log"
	"io"
	"io/ioutil"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"strings"

	"github.com/PuerkitoBio/goquery"
	"github.com/google/go-tika/tika"
	"github.com/mecenat/solr"
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
	NumOfPage    int64
	Curretpage   int64
	Lastpage     int64
}
type KWRIF struct {
	NumFound        int64
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

	fmt.Println("clear documents?(Y/N)")
	scanner := bufio.NewScanner(os.Stdin)
	for scanner.Scan() {
		if scanner.Text() == "Y" {
			break
		}
		if scanner.Text() == "N" {
			return nil
		}
	}

	ctx := context.Background()
	conn, err := solr.NewConnection(uriaddress, corename, http.DefaultClient)
	if err != nil {
		log.Fatal().
			Err(err).
			Msgf("Connection error.")
	}
	slr, err := solr.NewSingleClient(conn)
	if err != nil {
		log.Fatal().
			Err(err).
			Msgf("Not create solr client.")
	}

	_, err = slr.Clear(ctx)
	if err != nil {
		log.Fatal().Err(err)
	}

	return nil
}

// SolrQuery は、Solrに検索を投げます
// TODO: 引数要改良 、solrに接続する箇所も改良。毎回接続するのは問題。
func SolrQuery(uriaddress string, corename string, qs string) (*solr.Response, error) {
	ctx := context.Background()
	conn, err := solr.NewConnection(uriaddress, corename, http.DefaultClient)
	if err != nil {
		log.Fatal().
			Err(err).
			Msgf("Connection error.")
		return nil, err
	}

	slr, err := solr.NewSingleClient(conn)
	if err != nil {
		log.Fatal().
			Err(err).
			Msgf("Not create solr client.")
		return nil, err
	}

	opts := &solr.ReadOptions{Rows: 20, Debug: solr.DebugTypeQuery}
	q := solr.NewQuery(opts)

	// cut whitespace and zenkaku space
	qs = strings.TrimRight(qs, " 　")
	if qs == "" {
		q.SetQuery("*:*")
	} else {
		q.SetQuery("contents:" + qs)
	}

	q.SetParam("hl", "true")
	q.SetParam("hl.fl", "title")
	q.SetParam("hl.simple.pre", "<em>")
	q.SetParam("hl.simple.post", "</em>")
	// But filter on any film of the horror genre
	//q.AddFilter("genre", "horror")
	// Then we set the sorting to happen descending based on the year property
	//q.SetSort("year desc")

	fmt.Println(q.String())

	//TODO: coreが無い場合にエラーに変なエラーになる（要調査）
	res, err := slr.Search(ctx, q)
	if err != nil {
		fmt.Println("debug7")
		log.Fatal().Err(err)
		return nil, err
	}

	fmt.Printf("NumFound/FetchDocs:%d/%d\n", res.Data.NumFound, len(res.Data.Docs))
	fmt.Printf("Highlighting:%d\n", len(res.Data.Docs))

	return res, nil
}

// GenerateTextIndex は、テキスト形式のファイルの索引を作成します
// 1ファイルで Solr の1ドキュメントとする
func GenerateTextIndex(ctx context.Context, slr solr.Client, filename string, mediatype string, doc *goquery.Document) string {

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

	id := fmt.Sprintf("%s%d", filename, 0)
	wr := Material{ID: id, ObjectType: "FILE", Mediatype: mediatype, Foldername: foldername, Filename: basename, Contents: cells}

	//fmt.Printf("%+v\n", wr)
	//fmt.Println("try create to solr")

	_, err := slr.Create(ctx, &wr, &solr.WriteOptions{Commit: true})
	if err != nil {
		log.Fatal().Err(err)
	}
	fmt.Print(".")

	//fmt.Println(res.Header)

	return "ok"
}

// GenerateWordxIndex は MS WORD(.docx)形式のファイルの索引を作る
// 1ファイルで Solr の1ドキュメントとする
func GenerateWordxIndex(ctx context.Context, slr solr.Client, filename string, mediatype string, doc *goquery.Document) string {

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

	id := fmt.Sprintf("%s%d", filename, 0)
	wr := Material{ID: id, ObjectType: "FILE", Mediatype: mediatype, Foldername: foldername, Filename: basename, Title: title, Contents: cells}

	//fmt.Printf("%+v\n", wr)
	//fmt.Println("try create to solr")

	_, err := slr.Create(ctx, &wr, &solr.WriteOptions{Commit: true})
	if err != nil {
		log.Fatal().Err(err)
	}
	fmt.Print(".")

	//fmt.Println(res.Header)

	return "ok"
}

// GeneratePdfIndex は、PDF(.pdf)形式のファイルの索引を作る
// PDFの1ページで Solr の1ドキュメントとする
func GeneratePdfIndex(ctx context.Context, slr solr.Client, filename string, mediatype string, doc *goquery.Document) string {

	basename := filepath.Base(filename)
	foldername := filepath.Dir(filename)

	title := basename

	// Find the review items
	doc.Find("div.page").Each(func(rindex int, pageselection *goquery.Selection) {
		// For each item found
		text := strings.Replace(pageselection.Text(), "\n", " ", -1)
		text = strings.Replace(text, " ", "", -1)
		//text = rep_space.ReplaceAllString(text, "")

		//fmt.Println(text)
		cells := []string{text}

		id := fmt.Sprintf("%s%d", filename, rindex)
		doc := Material{ID: id, ObjectType: "FILE", Mediatype: mediatype, Foldername: foldername, Filename: basename, Title: title, Contents: cells}

		//fmt.Printf("%+v\n", doc)
		//fmt.Println("try create to solr")

		_, err := slr.Create(ctx, &doc, &solr.WriteOptions{Commit: true})
		if err != nil {
			log.Fatal().Err(err)
		}
		fmt.Print(".")

		//fmt.Println(res.Header)
	})

	return "ok"
}

// GenerateExcelIndex は Excel(.xlsx)形式のファイルの索引を作る
// Excelの1行で Solr の1ドキュメントとする
func GenerateExcelIndex(ctx context.Context, slr solr.Client, filename string, mediatype string, doc *goquery.Document) string {
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
				cells := []string{}

				innerselection := rowselection.Find("td")
				innerselection.Each(func(cindex int, cellsel *goquery.Selection) {
					//fmt.Printf("cell %d %d: %s\n", rindex, cindex, cellsel.Text())

					cells = append(cells, cellsel.Text())
				})

				// 情報がある行のみ索引を作成する（空行は索引に含めない）
				if len(cells) > 0 {
					id := fmt.Sprintf("%s%s%d", filename, sheetname, rindex)

					doc := Material{ID: id, ObjectType: "FILE", Mediatype: mediatype, Foldername: foldername, Filename: basename, Sheetname: sheetname, Title: title, Contents: cells}

					//fmt.Printf("%+v\n", doc)
					//fmt.Println("try create to solr")

					_, err := slr.Create(ctx, &doc, &solr.WriteOptions{Commit: true})
					if err != nil {
						log.Fatal().Err(err)
					}
					fmt.Print(".")
				}
			})
		}

	})

	return "ok"
}

func ImportFromFile(files []string, tikaserveruri string, solrserveruri string, solrcorename string) error {

	ctx := context.Background()
	conn, err := solr.NewConnection(solrserveruri, solrcorename, http.DefaultClient)
	if err != nil {
		log.Fatal().Err(err)
		return err
	}

	slr, err := solr.NewSingleClient(conn)
	if err != nil {
		log.Fatal().Err(err)
		return err
	}
	err = slr.Ping(ctx)
	if err != nil {
		fmt.Printf("Solr core ping:ng (%s %s)\n", solrserveruri, solrcorename)
		return err
	}

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
		fmt.Print("@1")
		fmt.Print(body)

		//TODO: 拡張子とフォーマットのMAPから選択したい
		switch extname {
		case ".xlsx":
			GenerateExcelIndex(ctx, slr, filename, contentType, doc)
		case ".pdf":
			GeneratePdfIndex(ctx, slr, filename, contentType, doc)
		case ".docx":
			GenerateWordxIndex(ctx, slr, filename, contentType, doc)
		case ".txt":
			GenerateTextIndex(ctx, slr, filename, contentType, doc)
		default:
			fmt.Printf("\nunknown format: skip [%s]\n", filename)
		}
		fmt.Printf("\n")
		successCount++
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

	defer res.Body.Close()

	log.Debug().Msgf("%s", res.Header)

	// 読み取り
	buf, _ := ioutil.ReadAll(res.Body)

	// 文字コード判定
	//det := chardet.NewTextDetector()
	//detResult, _ := det.DetectBest(buf)

	// 文字コード変換
	bReader := bytes.NewReader(buf)
	//reader, _ := charset.NewReaderLabel(detResult.Charset, bReader)

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
