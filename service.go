package kassiscore

import (
	"context"
	"errors"
	"fmt"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strings"

	"github.com/PuerkitoBio/goquery"
	"github.com/google/go-tika/tika"
	"github.com/mecenat/solr"
)

type Material struct {
	ID         string   `json:"id"`
	Foldername string   `json:"foldername"`
	Filename   string   `json:"filename"`
	Sheetname  string   `json:"sheetname"`
	Mediatype  string   `json:"mediatype"`
	Cellvalues []string `json:"cellvalues"`
}

// Web用のレスポンス構造体
type KWRIF struct {
	NumFound        int64
	ResponseStatus  string
	ResponseMessage string
	Materials       []Material
}

const MEDIATYPE_EXCEL string = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
const MEDIATYPE_PDF string = "application/pdf"
const MEDIATYPE_TEXT string = "text/plain"

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
	mediatype := "File"
	switch extname {
	case ".xlsx":
		mediatype = MEDIATYPE_EXCEL
	case ".pdf":
		mediatype = MEDIATYPE_PDF
	case ".txt":
		mediatype = MEDIATYPE_TEXT
	default:
		mediatype = "File"
	}
	return mediatype
}

func SolrClearDocument(uriaddress string, corename string) error {

	ctx := context.Background()
	conn, err := solr.NewConnection(uriaddress, corename, http.DefaultClient)
	if err != nil {
		log.Fatal("connection error.")
	}
	slr, err := solr.NewSingleClient(conn)
	if err != nil {
		log.Fatal("not create solr client")
	}

	_, err = slr.Clear(ctx)
	if err != nil {
		log.Fatal(err)
	}
	//fmt.Println(res)

	return nil
}

//検索用関数
//TODO:そもそも必要なのか。。。
//引数要改良
//solrに接続する箇所も改良。毎回接続するのは問題。
func SolrQuery(uriaddress string, corename string, qs string) (*solr.Response, error) {

	ctx := context.Background()
	conn, err := solr.NewConnection(uriaddress, corename, http.DefaultClient)
	if err != nil {
		log.Fatal("connection error.")
	}
	slr, err := solr.NewSingleClient(conn)
	if err != nil {
		log.Fatal("not create solr client")
	}

	opts := &solr.ReadOptions{Rows: 20, Debug: solr.DebugTypeQuery}
	q := solr.NewQuery(opts)

	// cut whitespace and zenkaku space
	qs = strings.TrimRight(qs, " 　")
	if qs == "" {
		q.SetQuery("*:*")
	} else {
		q.SetQuery("cellvalues:" + qs)
	}
	// But filter on any film of the horror genre
	//q.AddFilter("genre", "horror")
	// Then we set the sorting to happen descending based on the year property
	//q.SetSort("year desc")

	//fmt.Println(q.String())

	// We fire a search providing as input our Query
	res, err := slr.Search(ctx, q)
	if err != nil {
		log.Fatal(err)
	}
	fmt.Printf("NumFound/FetchDocs:%d/%d\n", res.Data.NumFound, len(res.Data.Docs))

	return res, nil
}

/*
 * PDF(.pdf)形式のファイルの索引を作る
 * PDFの1ページで Solr の1ドキュメントとする
 */
func GeneratePdfIndex(ctx context.Context, slr solr.Client, filename string, mediatype string, doc *goquery.Document) string {

	fmt.Println("pdf")
	//rep_space := regexp.MustCompile(`/\s{2,}/`)

	// Find the review items
	doc.Find("div.page").Each(func(rindex int, pageselection *goquery.Selection) {
		// For each item found
		text := strings.Replace(pageselection.Text(), "\n", " ", -1)
		text = strings.Replace(text, " ", "", -1)
		//text = rep_space.ReplaceAllString(text, "")

		//fmt.Println(text)
		cells := []string{text}

		basename := filepath.Base(filename)
		foldername := filepath.Dir(filename)

		id := fmt.Sprintf("%s%d", filename, rindex)
		doc := Material{ID: id, Mediatype: mediatype, Foldername: foldername, Filename: basename, Cellvalues: cells}

		//fmt.Printf("%+v\n", doc)
		//fmt.Println("try create to solr")

		_, err := slr.Create(ctx, &doc, &solr.WriteOptions{Commit: true})
		if err != nil {
			log.Fatal(err)
		}
		fmt.Print(".")

		//fmt.Println(res.Header)
	})

	return "ok"
}

/*
 * Excel(.xlsx)形式のファイルの索引を作る
 * Excelの1行で Solr の1ドキュメントとする
 */
func GenerateExcelIndex(ctx context.Context, slr solr.Client, filename string, mediatype string, doc *goquery.Document) string {
	//TODO:対象外とするシート名の受け渡しは要改良
	excludesheetnames := []string{"注意書き"}

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
					basename := filepath.Base(filename)
					foldername := filepath.Dir(filename)

					doc := Material{ID: id, Mediatype: mediatype, Foldername: foldername, Filename: basename, Sheetname: sheetname, Cellvalues: cells}

					//fmt.Printf("%+v\n", doc)
					//fmt.Println("try create to solr")

					_, err := slr.Create(ctx, &doc, &solr.WriteOptions{Commit: true})
					if err != nil {
						log.Fatal(err)
					}
					fmt.Print(".")
				}
			})
		}

	})

	return "ok"
}

func ImportFromFile(files []string) error {
	tikaserveruri := "http://localhost:9998"
	solrserveruri := "http://localhost:8983"
	solrcorename := "kassiscore"

	ctx := context.Background()
	conn, err := solr.NewConnection(solrserveruri, solrcorename, http.DefaultClient)
	if err != nil {
		log.Fatal(err)
	}

	slr, err := solr.NewSingleClient(conn)
	if err != nil {
		log.Fatal(err)
	}

	//Create connection with tika server
	client := tika.NewClient(nil, tikaserveruri)

	success_count := 0
	for _, filename := range files {
		//Get the file and open it
		file, err := os.Open(filename)
		if err != nil {
			return errors.New(fmt.Sprintf("os: Unable to open file [%s]", filename))
		}

		//Close the file
		defer file.Close()

		//Read the content from file
		body, err := client.Parse(context.Background(), file)
		if err != nil {
			log.Fatal(err)
		}

		extname := filepath.Ext(filename)
		mediatype := ExtnameToMediaType(extname)

		// Load the HTML document
		doc, err := goquery.NewDocumentFromReader(strings.NewReader(body))
		if err != nil {
			log.Fatal(err)
		}

		//fmt.Print(body)

		switch extname {
		case ".xlsx":
			GenerateExcelIndex(ctx, slr, filename, mediatype, doc)
		case ".pdf":
			GeneratePdfIndex(ctx, slr, filename, mediatype, doc)
		default:
			fmt.Printf("skip [%s]\n", filename)
		}
		success_count++
	}

	fmt.Printf("success_count=%d\n", success_count)

	return nil
}
