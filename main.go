package main

import (
	"context"
	"encoding/json"
	"flag"
	"fmt"
	"io/fs"
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
	Filename   string   `json:"filename"`
	Sheetname  string   `json:"sheetname"`
	Mediatype  string   `json:"mediatype"`
	Cellvalues []string `json:"cellvalues"`
}

func (f *Material) ToMap() (doc map[string]interface{}, err error) {
	materialBytes, err := json.Marshal(f)
	if err != nil {
		return
	}
	err = json.Unmarshal(materialBytes, &doc)
	return
}

func ExtnameToMediaType(extname string) string {
	mediatype := "File"
	switch extname {
	case ".xlsx":
		mediatype = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
	case ".pdf":
		mediatype = "application/pdf"
	case ".txt":
		mediatype = "text/plain"
	default:
		mediatype = "File"
	}
	return mediatype
}

/*
 * PDF(.pdf)形式のファイルの索引を作る
 * PDFの1ページで Solr の1ドキュメントとする
 */
func GeneratePdfIndex(ctx context.Context, slr solr.Client, filename string, mediatype string, doc *goquery.Document) string {
	doc.Find("body div").Each(func(rindex int, sheetselection *goquery.Selection) {
		// Find the review items
		sheetselection.Find("page").Each(func(rindex int, pageselection *goquery.Selection) {
			// For each item found
			cells := []string{pageselection.Text()}

			id := fmt.Sprintf("%s%d", filename, rindex)
			doc := Material{ID: id, Mediatype: mediatype, Filename: filename, Cellvalues: cells}

			//fmt.Printf("%+v\n", doc)
			fmt.Println("try create to solr")

			res, err := slr.Create(ctx, &doc, &solr.WriteOptions{Commit: true})
			if err != nil {
				log.Fatal(err)
			}
			fmt.Println(res.Header)
		})
	})

	return "ok"
}

/*
 * Excel(.xlsx)形式のファイルの索引を作る
 * Excelの1行で Solr の1ドキュメントとする
 */
func GenerateExcelIndex(ctx context.Context, slr solr.Client, filename string, mediatype string, doc *goquery.Document) string {
	doc.Find("body div").Each(func(rindex int, sheetselection *goquery.Selection) {
		sheetname := sheetselection.Find("h1").Text()

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
				doc := Material{ID: id, Mediatype: mediatype, Filename: filename, Sheetname: sheetname, Cellvalues: cells}

				//fmt.Printf("%+v\n", doc)
				fmt.Println("try create to solr")

				_, err := slr.Create(ctx, &doc, &solr.WriteOptions{Commit: true})
				if err != nil {
					log.Fatal(err)
				}
			}
		})
	})

	return "ok"
}

func main() {

	fmt.Println("Main function started")
	/*
		var (
			targetfilename = flag.String("f", "target filename or foldername.", "Message")
		)
	*/
	flag.Parse()

	if len(flag.Args()) != 1 {
		fmt.Println("Error: no filepath or directory")
		os.Exit(1)
	}

	files := []string{}

	if f, err := os.Stat(flag.Arg(0)); os.IsNotExist(err) || f.IsDir() {
		if f, err := os.Stat(flag.Arg(0)); os.IsNotExist(err) || !f.IsDir() {
			fmt.Printf("Error: No such file or directory (%s)\n", flag.Arg(0))
			os.Exit(2)
		} else {

			err := filepath.WalkDir(flag.Arg(0), func(path string, info fs.DirEntry, err error) error {
				if err != nil {
					fmt.Println("Error: failed filepath.WalkDir")
					fmt.Println(err)
					os.Exit(3)
				}

				if info.IsDir() {
					return nil
				}

				files = append(files, path)
				return nil
			})
			if err != nil {
				fmt.Println("Error: failed filepath.WalkDir")
				fmt.Println(err)
				os.Exit(3)
			}
		}
	} else {
		// TODO: fullpath にしても良いかな。
		files = append(files, flag.Arg(0))
	}

	fmt.Print(files)
	fmt.Print("\n")

	//filename := "/mnt/c/Users/tmpz8/Documents/kassis/examples/fushin_qa.xlsx"
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

	for _, filename := range files {
		//Get the file and open it
		file, err := os.Open(filename)
		if err != nil {
			fmt.Println(err)
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
		}
	}
}
